<?php

namespace Tests\Feature;

use App\Http\Controllers\PostController;
use App\Models\Post;
use App\Models\User;
use App\Services\OpenAiBlogGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class PostGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests should see the auth entry points on the homepage.
     */
    public function test_home_page_loads_for_guests(): void
    {
        $this->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Generate and share AI blog posts on any topic.')
            ->assertSee('POST GENERATOR')
            ->assertSee('Example blog')
            ->assertSee('Recent posts:')
            ->assertSee('Generate free draft')
            ->assertSee('Login')
            ->assertSee('Register')
            ->assertSee('AI model')
            ->assertSee('GPT-5 mini')
            ->assertSee('Guest trials use GPT-5 mini for the most reliable response time.')
            ->assertDontSee('GPT-5.4')
            ->assertDontSee('GPT-5.2')
            ->assertDontSee('GPT-5 nano')
            ->assertSee('Enhance SEO')
            ->assertDontSee('Prompt-to-post publishing')
            ->assertDontSee('Studio pulse');
    }

    /**
     * The homepage should show a load-more control when more than 6 recent posts exist.
     */
    public function test_home_page_shows_load_more_control_for_recent_posts(): void
    {
        foreach (range(1, 13) as $index) {
            Post::factory()->create([
                'title' => 'Recent '.$index,
                'slug' => 'recent-'.$index,
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Recent 1')
            ->assertSee('Recent 2')
            ->assertSee('Recent 3')
            ->assertSee('Recent 4')
            ->assertSee('Recent 5')
            ->assertSee('Recent 6')
            ->assertDontSee('Recent 7')
            ->assertSee('Load more');
    }

    /**
     * The legacy studio route should redirect to the homepage.
     */
    public function test_legacy_studio_route_redirects_to_home(): void
    {
        $this->get('/studio')
            ->assertRedirect(route('posts.index'));
    }

    /**
     * Guests should be redirected to login before generating posts.
     */
    public function test_guest_cannot_generate_a_post(): void
    {
        $this->post(route('posts.store'), [
            'topic' => 'How AI can improve Laravel blog publishing',
            'keywords' => 'OpenAI, editorial workflow, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5-mini',
            'enhance_seo' => '1',
        ])->assertRedirect(route('login'));
    }

    /**
     * Guest previews should require a reCAPTCHA token when Enterprise protection is enabled.
     */
    public function test_guest_preview_requires_recaptcha_when_enabled(): void
    {
        $this->enableRecaptcha();

        $response = $this->from(route('posts.index'))->post(route('posts.preview'), [
            'topic' => 'How AI can improve Laravel blog publishing',
            'keywords' => 'OpenAI, editorial workflow, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5-mini',
            'enhance_seo' => '1',
        ]);

        $response->assertRedirect(route('posts.index'));
        $response->assertSessionHasErrors([
            'generation' => 'Complete the security check and try again.',
        ]);
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * Guests should be able to generate a single preview draft before registration.
     */
    public function test_guest_can_generate_a_single_preview_draft(): void
    {
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-5-mini');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output_text' => json_encode($this->generatedArticleFixture(), JSON_THROW_ON_ERROR),
            ], 200),
        ]);

        $response = $this->post(route('posts.preview'), [
            'topic' => 'How AI can improve Laravel blog publishing',
            'keywords' => 'OpenAI, editorial workflow, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5-mini',
            'enhance_seo' => '1',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && data_get($request->data(), 'model') === 'gpt-5-mini';
        });

        $response->assertRedirect(route('posts.index').'#guest-preview');
        $response->assertSessionHas(PostController::GUEST_PREVIEW_SESSION_KEY);
        $response->assertSessionHas(PostController::GUEST_GENERATION_COUNT_SESSION_KEY, 1);
        $this->assertDatabaseCount('posts', 0);

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Your draft is ready to publish')
            ->assertSee('How AI Changes Laravel Blog Workflows')
            ->assertSee('Start with a sharper editorial brief')
            ->assertSee('Closing takeaway')
            ->assertSee('You can read this 1 article now. Create an account or sign in to share it and generate more articles.')
            ->assertSee('Sign in')
            ->assertSee('Register');
    }

    /**
     * Guests should see a validation error instead of a 500 if generation times out.
     */
    public function test_guest_preview_timeout_returns_to_home_with_error_without_consuming_trial(): void
    {
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-5-mini');

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $response = $this->from(route('posts.index'))->post(route('posts.preview'), [
            'topic' => 'School holidays',
            'keywords' => 'term dates, family travel, school breaks',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5-mini',
            'enhance_seo' => '1',
        ]);

        $response->assertRedirect(route('posts.index'));
        $response->assertSessionHasErrors([
            'generation' => 'Generation took too long to complete. Try again, choose a faster model, or shorten the brief.',
        ]);
        $response->assertSessionMissing(PostController::GUEST_PREVIEW_SESSION_KEY);
        $response->assertSessionMissing(PostController::GUEST_GENERATION_COUNT_SESSION_KEY);
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * Guests should see a friendly validation error if OpenAI rate-limits generation.
     */
    public function test_guest_preview_openai_rate_limit_returns_to_home_with_error_without_consuming_trial(): void
    {
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-5-mini');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached.',
                ],
            ], 429),
        ]);

        $response = $this->from(route('posts.index'))->post(route('posts.preview'), [
            'topic' => 'School holidays',
            'keywords' => 'term dates, family travel, school breaks',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5-mini',
            'enhance_seo' => '1',
        ]);

        $response->assertRedirect(route('posts.index'));
        $response->assertSessionHasErrors([
            'generation' => 'Generation is temporarily rate-limited. Please wait a moment and try again.',
        ]);
        $response->assertSessionMissing(PostController::GUEST_PREVIEW_SESSION_KEY);
        $response->assertSessionMissing(PostController::GUEST_GENERATION_COUNT_SESSION_KEY);
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * Generation routes should redirect back with a usable error if a 429 exception escapes.
     */
    public function test_guest_preview_429_exception_redirects_back_with_generation_error(): void
    {
        $this->app->instance(OpenAiBlogGenerator::class, new class extends OpenAiBlogGenerator
        {
            public function generate(array $attributes): array
            {
                throw new TooManyRequestsHttpException(null, 'Too Many Requests');
            }
        });

        $response = $this->from(route('posts.index'))->post(route('posts.preview'), [
            'topic' => 'School holidays',
            'keywords' => 'term dates, family travel, school breaks',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5-mini',
            'enhance_seo' => '1',
        ]);

        $response->assertRedirect(route('posts.index'));
        $response->assertSessionHasErrors([
            'generation' => 'Too many generation attempts right now. Please wait a few minutes and try again.',
        ]);
        $response->assertSessionMissing(PostController::GUEST_PREVIEW_SESSION_KEY);
        $response->assertSessionMissing(PostController::GUEST_GENERATION_COUNT_SESSION_KEY);
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * Guests should not be able to generate a second preview in the same session.
     */
    public function test_guest_cannot_generate_a_second_preview_draft_in_the_same_session(): void
    {
        $response = $this->withSession([
            PostController::GUEST_GENERATION_COUNT_SESSION_KEY => 1,
        ])->post(route('posts.preview'), [
            'topic' => 'How AI can improve Laravel blog publishing',
            'keywords' => 'OpenAI, editorial workflow, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5-mini',
            'enhance_seo' => '1',
        ]);

        $response->assertRedirect(route('posts.index'));
        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * Verified users should be able to publish a saved guest draft.
     */
    public function test_verified_user_can_publish_a_saved_guest_draft(): void
    {
        $user = User::factory()->create([
            'name' => 'writer-preview',
            'email' => 'writer-preview@example.com',
            'generated_posts_count' => 0,
        ]);

        $response = $this->withSession([
            PostController::GUEST_PREVIEW_SESSION_KEY => $this->guestPreviewPayload(),
            PostController::GUEST_GENERATION_COUNT_SESSION_KEY => 1,
        ])->actingAs($user)->post(route('posts.preview.publish'));

        $post = Post::first();

        $this->assertNotNull($post);
        $response->assertRedirect(route('posts.show', $post));
        $response->assertSessionMissing(PostController::GUEST_PREVIEW_SESSION_KEY);
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->getKey(),
            'title' => 'How AI Changes Laravel Blog Workflows',
            'topic' => 'How AI can improve Laravel blog publishing',
        ]);
        $this->assertSame(1, $user->fresh()->generated_posts_count);
    }

    /**
     * Signed-in article generation should require a reCAPTCHA token when Enterprise protection is enabled.
     */
    public function test_verified_user_generation_requires_recaptcha_when_enabled(): void
    {
        $this->enableRecaptcha();

        $user = User::factory()->create([
            'name' => 'secure-writer',
            'email' => 'secure-writer@example.com',
        ]);

        $response = $this->actingAs($user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), [
                'topic' => 'How AI can improve Laravel blog publishing',
                'keywords' => 'OpenAI, editorial workflow, automation',
                'tone' => 'insightful',
                'audience' => 'general',
                'depth' => 'balanced',
                'model' => 'gpt-5-mini',
                'enhance_seo' => '1',
            ]);

        $response->assertRedirect(route('posts.index'));
        $response->assertSessionHasErrors([
            'generation' => 'Complete the security check and try again.',
        ]);
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * Non-subscribers should not be able to request the subscriber-only GPT-5.4 model.
     */
    public function test_non_subscriber_cannot_request_gpt_5_4(): void
    {
        $user = User::factory()->create([
            'name' => 'writer-no-pro',
            'email' => 'writer-no-pro@example.com',
            'generated_posts_count' => 0,
        ]);

        $response = $this->actingAs($user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), [
                'topic' => 'How AI can improve Laravel blog publishing',
                'keywords' => 'OpenAI, editorial workflow, automation',
                'tone' => 'insightful',
                'audience' => 'general',
                'depth' => 'balanced',
                'model' => 'gpt-5.4',
                'enhance_seo' => '1',
            ]);

        $response->assertRedirect(route('posts.index'));
        $response->assertSessionHasErrors('model');
        $this->assertDatabaseCount('posts', 0);
    }

    /**
     * Subscribers should be able to generate with GPT-5.4.
     */
    public function test_subscriber_can_generate_with_gpt_5_4(): void
    {
        Config::set('services.openai.key', 'test-key');

        $user = User::factory()->create([
            'name' => 'writer-pro',
            'email' => 'writer-pro@example.com',
            'generated_posts_count' => 5,
            'subscription_status' => 'active',
            'subscription_plan' => 'monthly',
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output_text' => json_encode($this->generatedArticleFixture(), JSON_THROW_ON_ERROR),
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'topic' => 'How AI can improve Laravel blog publishing',
            'keywords' => 'OpenAI, editorial workflow, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5.4',
            'enhance_seo' => '1',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && data_get($request->data(), 'model') === 'gpt-5.4';
        });

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->getKey(),
            'model' => 'gpt-5.4',
        ]);
    }

    /**
     * Users with paid credits should consume one credit after the free quota is exhausted.
     */
    public function test_user_with_credit_balance_consumes_one_credit_per_generated_post(): void
    {
        Config::set('services.openai.key', 'test-key');

        $user = User::factory()->create([
            'name' => 'writer-credits',
            'email' => 'writer-credits@example.com',
            'generated_posts_count' => 5,
            'credit_balance' => 25,
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output_text' => json_encode($this->generatedArticleFixture(), JSON_THROW_ON_ERROR),
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'topic' => 'How AI can improve Laravel blog publishing',
            'keywords' => 'OpenAI, editorial workflow, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5.2',
            'enhance_seo' => '1',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertSame(24, $user->creditBalance());
        $this->assertSame(6, $user->generated_posts_count);
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->getKey(),
            'model' => 'gpt-5.2',
        ]);
    }

    /**
     * Users who have exhausted the free tier must subscribe before generating again.
     */
    public function test_user_without_subscription_cannot_generate_after_reaching_the_free_limit(): void
    {
        $user = User::factory()->create([
            'name' => 'writer-limit',
            'email' => 'writer-limit@example.com',
            'generated_posts_count' => 5,
        ]);

        $this->actingAs($user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), [
                'topic' => 'How AI can improve Laravel blog publishing',
                'keywords' => 'OpenAI, editorial workflow, automation',
                'tone' => 'insightful',
                'audience' => 'general',
                'depth' => 'balanced',
                'model' => 'gpt-5-mini',
                'enhance_seo' => '1',
            ])
            ->assertRedirect(route('posts.index'));
    }

    /**
     * Authenticated users must verify their email before generating posts.
     */
    public function test_unverified_user_cannot_generate_a_post(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'writer01',
            'email' => 'writer01@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'topic' => 'How AI can improve Laravel blog publishing',
                'keywords' => 'OpenAI, editorial workflow, automation',
                'tone' => 'insightful',
                'audience' => 'general',
                'depth' => 'balanced',
                'model' => 'gpt-5-mini',
                'enhance_seo' => '1',
            ])
            ->assertRedirect(route('verification.notice'));
    }

    /**
     * Authenticated users should be able to generate and store a post.
     */
    public function test_prompt_submission_generates_and_stores_a_post(): void
    {
        Config::set('services.openai.key', 'test-key');
        Config::set('services.openai.model', 'gpt-5-mini');

        $user = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output_text' => json_encode($this->generatedArticleFixture(), JSON_THROW_ON_ERROR),
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'topic' => 'How AI can improve Laravel blog publishing',
            'keywords' => 'OpenAI, editorial workflow, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'model' => 'gpt-5.2',
            'enhance_seo' => '1',
        ]);

        Http::assertSent(function ($request): bool {
            $prompt = data_get($request->data(), 'input.1.content');
            $model = data_get($request->data(), 'model');

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $model === 'gpt-5.2'
                && is_string($prompt)
                && str_contains($prompt, 'SEO enhancement: enabled.')
                && str_contains($prompt, 'Use the strongest keyword or topic phrase naturally in the title, intro, and at least one section heading.');
        });

        $post = Post::first();

        $this->assertNotNull($post);
        $response->assertRedirect(route('posts.show', $post));
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->getKey(),
            'title' => 'How AI Changes Laravel Blog Workflows',
            'topic' => 'How AI can improve Laravel blog publishing',
            'model' => 'gpt-5.2',
        ]);
        $this->assertSame(1, $user->fresh()->generated_posts_count);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('How AI Changes Laravel Blog Workflows')
            ->assertSee('The win is not automatic writing.');
    }

    /**
     * Post owners should see the article sharing options on their own posts.
     */
    public function test_post_owner_sees_share_actions_on_article_page(): void
    {
        $owner = User::factory()->create([
            'name' => 'share-owner',
            'email' => 'share-owner@example.com',
        ]);

        $post = Post::factory()->create([
            'user_id' => $owner->getKey(),
        ]);

        $this->actingAs($owner)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Share this post')
            ->assertSee('Facebook')
            ->assertSee('twitter.com/intent/tweet', false)
            ->assertSee('Reddit')
            ->assertSee('LinkedIn')
            ->assertSee('Email article')
            ->assertSee('Copy link')
            ->assertSee('mailto:', false);
    }

    /**
     * Guests should not see owner-only share actions on article pages.
     */
    public function test_guest_does_not_see_share_actions_on_article_page(): void
    {
        $owner = User::factory()->create([
            'name' => 'share-owner-guest-check',
            'email' => 'share-owner-guest-check@example.com',
        ]);

        $post = Post::factory()->create([
            'user_id' => $owner->getKey(),
        ]);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertDontSee('Share this post')
            ->assertDontSee('twitter.com/intent/tweet', false)
            ->assertDontSee('Email article')
            ->assertDontSee('Copy link')
            ->assertDontSee('mailto:', false);
    }

    /**
     * Signed-in users should only see share actions on their own posts.
     */
    public function test_non_owner_does_not_see_share_actions_on_article_page(): void
    {
        $owner = User::factory()->create([
            'name' => 'share-owner-user-check',
            'email' => 'share-owner-user-check@example.com',
        ]);
        $viewer = User::factory()->create([
            'name' => 'share-viewer',
            'email' => 'share-viewer@example.com',
        ]);

        $post = Post::factory()->create([
            'user_id' => $owner->getKey(),
        ]);

        $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertDontSee('Share this post')
            ->assertDontSee('Facebook')
            ->assertDontSee('twitter.com/intent/tweet', false)
            ->assertDontSee('LinkedIn')
            ->assertDontSee('Email article')
            ->assertDontSee('Copy link');
    }

    /**
     * The admin account should be able to access share actions on legacy posts without an owner.
     */
    public function test_admin_sees_share_actions_on_legacy_unowned_article_page(): void
    {
        $admin = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin-share@example.com',
        ]);

        $post = Post::factory()->create([
            'user_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Share this post')
            ->assertSee('Facebook')
            ->assertSee('twitter.com/intent/tweet', false)
            ->assertSee('Reddit')
            ->assertSee('LinkedIn')
            ->assertSee('Email article')
            ->assertSee('Copy link')
            ->assertSee('mailto:', false);
    }

    /**
     * Article pages should show a load-more control when more than 6 related posts exist.
     */
    public function test_article_page_shows_load_more_control_for_related_posts(): void
    {
        $currentPost = Post::factory()->create([
            'title' => 'Current article',
            'slug' => 'current-article',
        ]);

        foreach (range(1, 13) as $index) {
            Post::factory()->create([
                'title' => 'Related '.$index,
                'slug' => 'related-'.$index,
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $this->get(route('posts.show', $currentPost))
            ->assertOk()
            ->assertSee('Related 1')
            ->assertSee('Related 2')
            ->assertSee('Related 3')
            ->assertSee('Related 4')
            ->assertSee('Related 5')
            ->assertSee('Related 6')
            ->assertDontSee('Related 7')
            ->assertDontSee('Related 8')
            ->assertSee('Load more');
    }

    /**
     * The related-posts endpoint should return the next batch of 6 cards.
     */
    public function test_related_posts_endpoint_returns_next_batch_of_cards(): void
    {
        $currentPost = Post::factory()->create([
            'title' => 'Current article',
            'slug' => 'current-article',
        ]);

        foreach (range(1, 13) as $index) {
            Post::factory()->create([
                'title' => 'Related '.$index,
                'slug' => 'related-'.$index,
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $response = $this->getJson(route('posts.related', [
            'post' => $currentPost,
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertJson([
                'count' => 6,
                'has_more' => true,
                'next_page' => 3,
            ]);

        $html = (string) $response->json('html');

        $this->assertStringContainsString('Related 7', $html);
        $this->assertStringContainsString('Related 8', $html);
        $this->assertStringContainsString('Related 9', $html);
        $this->assertStringContainsString('Related 10', $html);
        $this->assertStringContainsString('Related 11', $html);
        $this->assertStringContainsString('Related 12', $html);
        $this->assertStringNotContainsString('Related 13', $html);
    }

    /**
     * The homepage recent-posts endpoint should return the next batch of 6 cards.
     */
    public function test_recent_posts_endpoint_returns_next_batch_of_cards(): void
    {
        foreach (range(1, 13) as $index) {
            Post::factory()->create([
                'title' => 'Recent '.$index,
                'slug' => 'recent-'.$index,
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $response = $this->getJson(route('posts.recent', [
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertJson([
                'count' => 6,
                'has_more' => true,
                'next_page' => 3,
            ]);

        $html = (string) $response->json('html');

        $this->assertStringContainsString('Recent 7', $html);
        $this->assertStringContainsString('Recent 8', $html);
        $this->assertStringContainsString('Recent 9', $html);
        $this->assertStringContainsString('Recent 10', $html);
        $this->assertStringContainsString('Recent 11', $html);
        $this->assertStringContainsString('Recent 12', $html);
        $this->assertStringNotContainsString('Recent 13', $html);
    }

    /**
     * Return a reusable generated article fixture.
     *
     * @return array<string, mixed>
     */
    protected function generatedArticleFixture(): array
    {
        return [
            'title' => 'How AI Changes Laravel Blog Workflows',
            'excerpt' => 'AI can speed up ideation, structure, and first drafts without removing editorial judgement. Laravel makes it straightforward to capture prompts and turn them into stored content.',
            'intro' => 'The biggest shift is not that AI writes every word. It is that teams stop starting from an empty page and start with a structured first version they can refine quickly.',
            'sections' => [
                [
                    'heading' => 'Start with a sharper editorial brief',
                    'body' => 'The better the brief, the better the first draft. A topic, a few keywords, and a defined audience give the model enough context to stay focused.'."\n\n".'That means less cleanup after generation and more time spent polishing insight, examples, and tone.',
                ],
                [
                    'heading' => 'Use Laravel to keep the workflow safe',
                    'body' => 'A server-side integration keeps the API key out of the browser and gives you full control over validation, errors, and persistence.'."\n\n".'That is exactly the kind of glue Laravel handles well with forms, controllers, and database models.',
                ],
                [
                    'heading' => 'Publishing becomes a repeatable system',
                    'body' => 'Once a structured response is saved, the front-end can render it consistently across cards, detail pages, and related content blocks.'."\n\n".'The result is a lightweight publishing system that feels custom rather than stitched together.',
                ],
                [
                    'heading' => 'Human review still matters',
                    'body' => 'AI is strongest when it gives you momentum, not when it replaces judgement. Editors still decide what deserves to go live.'."\n\n".'That review loop keeps the final post original, accurate, and aligned to the audience.',
                ],
            ],
            'takeaway' => 'The win is not automatic writing. The win is removing blank-page friction while keeping editorial control.',
            'tags' => ['Laravel', 'OpenAI', 'Content Ops'],
            'reading_time' => 6,
        ];
    }

    /**
     * Return the session payload used to publish a guest draft.
     *
     * @return array<string, mixed>
     */
    protected function guestPreviewPayload(): array
    {
        return [
            'topic' => 'How AI can improve Laravel blog publishing',
            'keywords' => 'OpenAI, editorial workflow, automation',
            'tone' => 'insightful',
            'audience' => 'general',
            'depth' => 'balanced',
            'enhance_seo' => true,
            'title' => 'How AI Changes Laravel Blog Workflows',
            'excerpt' => 'AI can speed up ideation, structure, and first drafts without removing editorial judgement. Laravel makes it straightforward to capture prompts and turn them into stored content.',
            'intro' => 'The biggest shift is not that AI writes every word. It is that teams stop starting from an empty page and start with a structured first version they can refine quickly.',
            'sections' => $this->generatedArticleFixture()['sections'],
            'takeaway' => 'The win is not automatic writing. The win is removing blank-page friction while keeping editorial control.',
            'tags' => ['Laravel', 'OpenAI', 'Content Ops'],
            'reading_time' => 6,
            'model' => 'gpt-5-mini',
        ];
    }

    /**
     * Enable Enterprise reCAPTCHA protection for route-level tests.
     */
    protected function enableRecaptcha(): void
    {
        Config::set('services.recaptcha.enterprise.enabled', true);
        Config::set('services.recaptcha.enterprise.site_key', 'test-site-key');
        Config::set('services.recaptcha.enterprise.api_key', 'test-api-key');
        Config::set('services.recaptcha.enterprise.project_id', 'blogfuel');
    }
}
