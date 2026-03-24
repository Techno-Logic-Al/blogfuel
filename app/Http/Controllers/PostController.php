<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiPostRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use App\Models\Post;
use App\Models\User;
use App\Services\OpenAiBlogGenerator;
use App\Services\RecaptchaEnterpriseVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class PostController extends Controller
{
    public const string GUEST_PREVIEW_SESSION_KEY = 'guest_preview_post';

    public const string GUEST_GENERATION_COUNT_SESSION_KEY = 'guest_generation_count';

    protected const int HOMEPAGE_POSTS_PER_PAGE = 6;

    protected const int RELATED_POSTS_PER_PAGE = 6;

    /**
     * Display the public blog homepage.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $homepagePostsPage = $this->homepagePostsPage();

        return view('posts.index', [
            'posts' => $homepagePostsPage['posts'],
            'postsHasMore' => $homepagePostsPage['has_more'],
            'postsNextPage' => $homepagePostsPage['next_page'],
            'tones' => config('blog.tones', []),
            'audiences' => config('blog.audiences', []),
            'depths' => config('blog.depths', []),
            'models' => $this->availableModels($user),
            'defaultModel' => $this->defaultModel($user),
            'guestTrialModel' => $this->guestTrialModel(),
            'billingPlans' => config('billing.plans', []),
            'creditPacks' => config('billing.credit_packs', []),
            'freeGenerationLimit' => (int) config('billing.free_generations', 5),
            'guestPreview' => $this->guestPreview($request),
            'guestCanGeneratePreview' => $this->guestCanGenerate($request),
            'guestFreeGenerationLimit' => $this->guestFreeGenerationLimit(),
            'premiumModelNotice' => $this->premiumModelNotice($user),
        ]);
    }

    /**
     * Generate and store a blog post.
     */
    public function store(
        StoreAiPostRequest $request,
        OpenAiBlogGenerator $generator,
        RecaptchaEnterpriseVerifier $recaptcha
    ): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($response = $this->verifyRecaptcha($request, $recaptcha, 'GENERATE_POST', 'generation')) {
            return $response;
        }

        try {
            $generated = $generator->generate($validated);
        } catch (TooManyRequestsHttpException $exception) {
            return back()
                ->withInput()
                ->withErrors(['generation' => 'Too many generation attempts right now. Please wait a few minutes and try again.']);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['generation' => $exception->getMessage()]);
        }

        $post = $this->createStoredPost(
            $user,
            $this->buildPostPayload($validated, $generated)
        );

        return redirect()
            ->route('posts.show', $post)
            ->with('status', 'Fresh AI-generated article saved to the blog.');
    }

    /**
     * Generate a single guest preview without publishing it.
     */
    public function preview(
        StoreAiPostRequest $request,
        OpenAiBlogGenerator $generator,
        RecaptchaEnterpriseVerifier $recaptcha
    ): RedirectResponse
    {
        if (! $this->guestCanGenerate($request)) {
            return redirect()
                ->route('posts.index')
                ->withInput()
                ->withErrors([
                    'generation' => 'Your free guest draft has already been used. Register or sign in to publish and continue.',
                ]);
        }

        $validated = $request->validated();
        $validated['model'] = $this->guestTrialModel();

        if ($response = $this->verifyRecaptcha($request, $recaptcha, 'GENERATE_POST', 'generation')) {
            return $response;
        }

        try {
            $generated = $generator->generate($validated);
        } catch (TooManyRequestsHttpException $exception) {
            return back()
                ->withInput()
                ->withErrors(['generation' => 'Too many generation attempts right now. Please wait a few minutes and try again.']);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['generation' => $exception->getMessage()]);
        }

        $request->session()->put(
            self::GUEST_PREVIEW_SESSION_KEY,
            $this->buildPostPayload($validated, $generated)
        );
        $request->session()->put(
            self::GUEST_GENERATION_COUNT_SESSION_KEY,
            min(
                $this->guestFreeGenerationLimit(),
                (int) $request->session()->get(self::GUEST_GENERATION_COUNT_SESSION_KEY, 0) + 1
            )
        );

        return redirect()
            ->to(route('posts.index').'#guest-preview')
            ->with('status', 'Your free guest draft is ready. Create an account to publish or share it.');
    }

    /**
     * Publish the saved guest preview to the blog for the signed-in user.
     */
    public function publishPreview(Request $request): RedirectResponse
    {
        $user = $request->user();
        $guestPreview = $this->guestPreview($request);

        if ($guestPreview === null) {
            return redirect()
                ->route('posts.index')
                ->withErrors(['generation' => 'No guest draft is waiting to be published.']);
        }

        $post = $this->createStoredPost($user, $guestPreview);

        $request->session()->forget(self::GUEST_PREVIEW_SESSION_KEY);

        return redirect()
            ->route('posts.show', $post)
            ->with('status', 'Your guest draft is now published to the blog.');
    }

    /**
     * Display a single blog post.
     */
    public function show(Post $post): View
    {
        $relatedPostsPage = $this->relatedPostsPage($post);

        return view('posts.show', [
            'post' => $post,
            'relatedPosts' => $relatedPostsPage['posts'],
            'relatedPostsHasMore' => $relatedPostsPage['has_more'],
            'relatedPostsNextPage' => $relatedPostsPage['next_page'],
        ]);
    }

    /**
     * Return another related-post batch for the article page load-more control.
     */
    public function related(Request $request, Post $post): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $relatedPostsPage = $this->relatedPostsPage($post, $page);

        return response()->json([
            'html' => view('posts.partials.related-post-cards', [
                'relatedPosts' => $relatedPostsPage['posts'],
            ])->render(),
            'count' => $relatedPostsPage['posts']->count(),
            'has_more' => $relatedPostsPage['has_more'],
            'next_page' => $relatedPostsPage['next_page'],
        ]);
    }

    /**
     * Return another homepage-post batch for the recent-post load-more control.
     */
    public function recent(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $homepagePostsPage = $this->homepagePostsPage($page);

        return response()->json([
            'html' => view('posts.partials.home-post-rows', [
                'posts' => $homepagePostsPage['posts'],
            ])->render(),
            'count' => $homepagePostsPage['posts']->count(),
            'has_more' => $homepagePostsPage['has_more'],
            'next_page' => $homepagePostsPage['next_page'],
        ]);
    }

    /**
     * Delete a stored blog post from the homepage list.
     */
    public function destroy(Post $post): RedirectResponse
    {
        abort_unless(auth()->id() === $post->user_id, 403);

        $post->delete();

        return redirect()
            ->route('posts.index')
            ->with('status', 'Post removed from recent output.');
    }

    /**
     * Ensure generated slugs stay unique.
     */
    protected function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug !== '' ? $baseSlug : 'ai-story';
        $counter = 2;

        while (Post::where('slug', $slug)->exists()) {
            $slug = ($baseSlug !== '' ? $baseSlug : 'ai-story').'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create and count a stored post for a user.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function createStoredPost(User $user, array $payload): Post
    {
        return DB::transaction(function () use ($payload, $user): Post {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $post = $lockedUser->posts()->create([
                'topic' => (string) $payload['topic'],
                'keywords' => $payload['keywords'] !== '' ? $payload['keywords'] : null,
                'tone' => (string) $payload['tone'],
                'audience' => (string) $payload['audience'],
                'depth' => (string) $payload['depth'],
                'title' => (string) $payload['title'],
                'slug' => $this->generateUniqueSlug((string) $payload['title']),
                'excerpt' => (string) $payload['excerpt'],
                'intro' => (string) $payload['intro'],
                'sections' => is_array($payload['sections']) ? $payload['sections'] : [],
                'takeaway' => (string) $payload['takeaway'],
                'tags' => is_array($payload['tags']) ? $payload['tags'] : [],
                'reading_time' => (int) $payload['reading_time'],
                'model' => (string) $payload['model'],
            ]);

            if ($lockedUser->shouldConsumeCreditForNextGeneration()) {
                $lockedUser->decrement('credit_balance');
            }

            $lockedUser->increment('generated_posts_count');

            return $post;
        });
    }

    /**
     * Build a reusable post payload from the brief and generated response.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $generated
     * @return array<string, mixed>
     */
    protected function buildPostPayload(array $attributes, array $generated): array
    {
        return [
            'topic' => $attributes['topic'],
            'keywords' => $attributes['keywords'] ?? '',
            'tone' => $attributes['tone'],
            'audience' => $attributes['audience'],
            'depth' => $attributes['depth'],
            'model' => $generated['model'],
            'enhance_seo' => (bool) ($attributes['enhance_seo'] ?? false),
            'title' => $generated['title'],
            'excerpt' => $generated['excerpt'],
            'intro' => $generated['intro'],
            'sections' => $generated['sections'],
            'takeaway' => $generated['takeaway'],
            'tags' => $generated['tags'],
            'reading_time' => $generated['reading_time'],
            'model' => $generated['model'],
        ];
    }

    /**
     * Return the saved guest preview from the session.
     *
     * @return array<string, mixed>|null
     */
    protected function guestPreview(Request $request): ?array
    {
        $preview = $request->session()->get(self::GUEST_PREVIEW_SESSION_KEY);

        return is_array($preview) ? $preview : null;
    }

    /**
     * Determine whether the guest can still generate a preview.
     */
    protected function guestCanGenerate(Request $request): bool
    {
        return $this->guestFreeGenerationLimit() > 0
            && (int) $request->session()->get(self::GUEST_GENERATION_COUNT_SESSION_KEY, 0) < $this->guestFreeGenerationLimit();
    }

    /**
     * Return the guest preview allowance.
     */
    protected function guestFreeGenerationLimit(): int
    {
        return max(0, (int) config('billing.guest_free_generations', 1));
    }

    /**
     * Return a paged homepage-post slice for the recent-post list.
     *
     * @return array{posts: \Illuminate\Support\Collection<int, Post>, has_more: bool, next_page: int|null}
     */
    protected function homepagePostsPage(int $page = 1): array
    {
        $page = max(1, $page);

        $posts = Post::query()
            ->latest()
            ->skip(($page - 1) * self::HOMEPAGE_POSTS_PER_PAGE)
            ->take(self::HOMEPAGE_POSTS_PER_PAGE + 1)
            ->get();

        $hasMore = $posts->count() > self::HOMEPAGE_POSTS_PER_PAGE;
        $visiblePosts = $posts->take(self::HOMEPAGE_POSTS_PER_PAGE)->values();

        return [
            'posts' => $visiblePosts,
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
        ];
    }

    /**
     * Return a paged related-post slice for the article detail page.
     *
     * @return array{posts: \Illuminate\Support\Collection<int, Post>, has_more: bool, next_page: int|null}
     */
    protected function relatedPostsPage(Post $post, int $page = 1): array
    {
        $page = max(1, $page);

        $posts = $this->relatedPostsQuery($post)
            ->skip(($page - 1) * self::RELATED_POSTS_PER_PAGE)
            ->take(self::RELATED_POSTS_PER_PAGE + 1)
            ->get();

        $hasMore = $posts->count() > self::RELATED_POSTS_PER_PAGE;
        $visiblePosts = $posts->take(self::RELATED_POSTS_PER_PAGE)->values();

        return [
            'posts' => $visiblePosts,
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
        ];
    }

    /**
     * Base query for the related posts shown beneath an article.
     */
    protected function relatedPostsQuery(Post $post): Builder
    {
        return Post::query()
            ->whereKeyNot($post->getKey())
            ->latest();
    }

    /**
     * Force guest trials onto the most reliable public model.
     */
    protected function guestTrialModel(): string
    {
        $availableModels = config('blog.models', []);

        return array_key_exists('gpt-5-mini', $availableModels)
            ? 'gpt-5-mini'
            : $this->defaultModel();
    }

    /**
     * Resolve the default model shown in the form.
     */
    protected function defaultModel(?User $user = null): string
    {
        $configuredModel = (string) config('services.openai.model');
        $availableModels = $this->availableModels($user);

        if (array_key_exists($configuredModel, $availableModels)) {
            return $configuredModel;
        }

        return array_key_exists('gpt-5-mini', $availableModels)
            ? 'gpt-5-mini'
            : (string) (array_key_first($availableModels) ?? 'gpt-5-mini');
    }

    /**
     * Return the model list visible to the current user.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function availableModels(?User $user = null): array
    {
        return $user?->availableModels() ?? config('blog.models', []);
    }

    /**
     * Return the subscriber-only model upsell note when relevant.
     */
    protected function premiumModelNotice(?User $user): ?string
    {
        if ($user === null || $user->hasUnlimitedGenerationAccess() || $user->hasActiveSubscription()) {
            return null;
        }

        return 'GPT-5.4 unlocks on Pro subscription plans only.';
    }
}
