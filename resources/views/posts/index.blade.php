@extends('layouts.app')
@inject('recaptcha', 'App\Services\RecaptchaEnterpriseVerifier')

@section('title', config('app.name'))
@section('meta_description', 'Generate and share AI blog posts on any topic.')

@section('content')
    @php($user = auth()->user())

    <section class="glass-panel form-panel" data-reveal>
        @if ($errors->has('generation') || $errors->has('billing') || $errors->has('recaptcha') || $errors->has('email'))
            <div class="alert-card alert-error">
                {{ $errors->first('generation') ?: $errors->first('billing') ?: $errors->first('recaptcha') ?: $errors->first('email') }}
            </div>
        @endif

        @auth
            @if ($user?->hasVerifiedEmail())
                @if ($guestPreview !== null)
                    <div class="usage-banner">
                        <div class="usage-copy">
                            <span class="eyebrow">Guest draft saved</span>
                            <h2>Publish your guest draft</h2>
                            <p>
                                @if ($user->hasUnlimitedGenerationAccess())
                                    Publish this saved guest draft to the blog. Your admin account keeps article generation unlimited.
                                @elseif ($user->hasActiveSubscription())
                                    Your guest draft is ready to go live, and your paid plan keeps generation unlimited afterwards.
                                @elseif ($user->freeGenerationsRemaining() > 0)
                                    Publish this saved guest draft to the blog. It will count as one of your free articles.
                                @else
                                    Publish this saved guest draft to the blog. It will use 1 article credit from your account.
                                @endif
                            </p>
                        </div>

                        <div class="usage-actions">
                            <form method="POST" action="{{ route('posts.preview.publish') }}">
                                @csrf
                                <button class="button button-primary" type="submit">Publish guest draft</button>
                            </form>
                        </div>
                    </div>

                    @include('posts.partials.guest-preview', [
                        'guestPreview' => $guestPreview,
                        'eyebrow' => 'Saved guest draft',
                    ])
                @endif

                @if ($user?->canGeneratePosts())
                    <div class="usage-banner @if ($user->hasActiveSubscription()) is-active-plan @endif">
                        <div class="usage-copy">
                            <span class="eyebrow">
                                @if ($user->hasUnlimitedGenerationAccess())
                                    Admin access
                                @else
                                    {{ $user->hasActiveSubscription() || $user->shouldConsumeCreditForNextGeneration() ? 'Paid usage' : 'Free usage' }}
                                @endif
                            </span>
                            <h2>
                                @if ($user->hasUnlimitedGenerationAccess())
                                    Unlimited free article generation
                                @elseif ($user->hasActiveSubscription())
                                    {{ $user->subscriptionPlanLabel() ?? 'Paid' }} plan active
                                @elseif ($user->shouldConsumeCreditForNextGeneration())
                                    {{ $user->creditBalance() }} paid posts remaining
                                @elseif ($user->freeGenerationsRemaining() > 0)
                                    {{ $user->freeGenerationsRemaining() }} of {{ $freeGenerationLimit }} free generations remaining
                                @else
                                    {{ $user->creditBalance() }} article credits remaining
                                @endif
                            </h2>
                            <p>
                                @if ($user->hasUnlimitedGenerationAccess())
                                    This admin account bypasses the normal free-generation cap and does not require a subscription.
                                @elseif ($user->hasActiveSubscription())
                                    Unlimited article generation is unlocked for this account.
                                @elseif ($user->shouldConsumeCreditForNextGeneration())
                                    You are now generating with your prepaid article credits. Keep going with your paid balance, or switch to Pro to unlock unlimited access and GPT-5.4.
                                @elseif ($user->freeGenerationsRemaining() > 0)
                                    After your fifth published article, switch to a credit pack or a Stripe Pro plan to keep using BlogFuel.
                                @else
                                    Your free quota is finished. Keep generating with your prepaid credits, or switch to Pro to unlock unlimited access and GPT-5.4.
                                @endif
                            </p>
                        </div>

                        @if ($user->hasActiveSubscription() && $user->canOpenBillingPortal())
                            <div class="usage-actions">
                                <form method="POST" action="{{ route('billing.portal') }}">
                                        @csrf
                                        <button class="button button-secondary" type="submit">Manage billing</button>
                                    </form>
                                </div>
                        @elseif (! $user->hasUnlimitedGenerationAccess() && $user->freeGenerationsRemaining() > 0)
                            <div class="quota-meter" aria-hidden="true">
                                <span
                                    class="quota-meter-fill"
                                    style="width: {{ min(100, ((int) $user->generated_posts_count / max(1, $freeGenerationLimit)) * 100) }}%;"
                                ></span>
                            </div>
                        @endif
                    </div>

                    <div class="panel-header">
                        <div>
                            <span class="eyebrow">POST GENERATOR</span>
                            <h1 id="prompt-form">Build your next post:</h1>
                        </div>
                    </div>

                    @include('posts.partials.brief-form', [
                        'action' => route('posts.store'),
                        'submitCopy' => 'Generate the article and publish it instantly.',
                        'submitLabel' => 'Generate and publish',
                        'modelHelperCopy' => $premiumModelNotice,
                    ])
                @else
                    <div class="billing-stack" id="plans">
                        <div class="guest-gate">
                            <h2>Your free articles are used up.</h2>
                            <p class="billing-intro-line">
                                @if ($guestPreview !== null)
                                    A saved guest draft is waiting. Choose article credits or a Pro plan to publish it and keep generating.
                                @else
                                    Choose article credits for occasional use, or switch to Pro for unlimited generation and GPT-5.4 access.
                                @endif
                            </p>

                            @if ($user->canOpenBillingPortal())
                                <div class="button-row">
                                    <form class="inline-form" method="POST" action="{{ route('billing.portal') }}">
                                        @csrf
                                        <button class="button button-secondary" type="submit">Manage billing</button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        <div class="pricing-section">
                            <div class="panel-header pricing-header">
                                <div>
                                    <span class="eyebrow">Credit packs</span>
                                    <h2>Buy article credits</h2>
                                </div>
                            </div>

                            <div class="plan-grid">
                                @foreach ($creditPacks as $planKey => $plan)
                                    @php($planConfigured = filled(config('services.stripe.secret')) && filled($plan['stripe_price_id'] ?? null))

                                    <article class="plan-card @if (($plan['badge'] ?? null) !== null) is-featured @endif">
                                        <div class="plan-copy">
                                            @if (! empty($plan['badge']))
                                                <span class="plan-badge">{{ $plan['badge'] }}</span>
                                            @endif
                                            <span class="eyebrow">{{ $plan['eyebrow'] ?? 'Credit pack' }}</span>
                                            <h3>{{ $plan['label'] }}</h3>
                                            <p class="plan-price">{{ $plan['price_label'] }}</p>
                                            <p>{{ $plan['description'] }}</p>
                                            @if (! empty($plan['capabilities']))
                                                <ul class="plan-feature-list">
                                                    @foreach ($plan['capabilities'] as $capability)
                                                        <li>{{ $capability }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>

                                        @if ($planConfigured)
                                            <form method="POST" action="{{ route('billing.checkout', $planKey) }}">
                                                @csrf
                                                <button class="button @if (($plan['badge'] ?? null) !== null) button-primary @else button-secondary @endif button-full" type="submit">
                                                    {{ $plan['cta'] }}
                                                </button>
                                            </form>
                                        @else
                                            <button class="button button-secondary button-full" type="button" disabled>Pack not configured</button>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>

                        <div class="pricing-section">
                            <div class="panel-header pricing-header">
                                <div>
                                    <span class="eyebrow">Pro plans</span>
                                    <h2>Choose your BlogFuel plan</h2>
                                </div>
                            </div>

                            <div class="plan-grid">
                                @foreach ($billingPlans as $planKey => $plan)
                                    @php($planConfigured = filled(config('services.stripe.secret')) && filled($plan['stripe_price_id'] ?? null))

                                    <article class="plan-card @if (($plan['badge'] ?? null) !== null) is-featured @endif">
                                        <div class="plan-copy">
                                            @if (! empty($plan['badge']))
                                                <span class="plan-badge">{{ $plan['badge'] }}</span>
                                            @endif
                                            <span class="eyebrow">{{ $plan['eyebrow'] ?? 'Pro plan' }}</span>
                                            <h3>{{ $plan['label'] }}</h3>
                                            <p class="plan-price">{{ $plan['price_label'] }}</p>
                                            <p>{{ $plan['description'] }}</p>
                                            @if (! empty($plan['capabilities']))
                                                <ul class="plan-feature-list">
                                                    @foreach ($plan['capabilities'] as $capability)
                                                        <li>{{ $capability }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>

                                        @if ($planConfigured)
                                            <form method="POST" action="{{ route('billing.checkout', $planKey) }}">
                                                @csrf
                                                <button class="button @if (($plan['badge'] ?? null) !== null) button-primary @else button-secondary @endif button-full" type="submit">
                                                    {{ $plan['cta'] }}
                                                </button>
                                            </form>
                                        @else
                                            <button class="button button-secondary button-full" type="button" disabled>Plan not configured</button>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>

                        <div class="billing-footnotes">
                            <p>Credit packs never auto-renew.</p>
                            <p>Pro plans renew automatically until cancelled.</p>
                            <p>GPT-5.4 is available on Pro plans only.</p>
                        </div>

                        @if (
                            blank(config('services.stripe.secret'))
                            || blank(config('billing.plans.monthly.stripe_price_id'))
                            || blank(config('billing.plans.annual.stripe_price_id'))
                            || blank(config('billing.credit_packs.pack_25.stripe_price_id'))
                            || blank(config('billing.credit_packs.pack_100.stripe_price_id'))
                        )
                            <p class="auth-hint">Stripe checkout is not fully configured yet. Add the Stripe secret key, both recurring Pro price IDs, and both one-time credit-pack price IDs in <code>.env</code>.</p>
                        @endif
                    </div>
                @endif
            @else
                <div class="guest-gate">
                    <h2>Verify your email to unlock the generator.</h2>
                    <p>
                        @if ($guestPreview !== null)
                            We sent a verification link to <strong>{{ $user?->email }}</strong>. Your guest draft is saved and waiting to be published as soon as you verify.
                        @else
                            We sent a verification link to <strong>{{ $user?->email }}</strong>. Open that link, then come back here to start generating articles.
                        @endif
                    </p>

                    <div class="button-row">
                        <form
                            class="inline-form"
                            method="POST"
                            action="{{ route('verification.send') }}"
                            @if ($recaptcha->enabled())
                                data-recaptcha-form
                                data-recaptcha-action="RESEND_VERIFICATION"
                            @endif
                        >
                            @csrf
                            <button class="button button-secondary" type="submit">Resend verification email</button>

                            @if ($recaptcha->enabled())
                                @include('partials.recaptcha-fields')
                            @endif
                        </form>
                    </div>

                    @if ($guestPreview !== null)
                        @include('posts.partials.guest-preview', [
                            'guestPreview' => $guestPreview,
                            'eyebrow' => 'Saved guest draft',
                            'accessNotice' => 'Verify your email, then come back to publish or share this draft.',
                        ])
                    @endif

                    @if (app()->isLocal() && in_array(config('mail.default'), ['log', 'failover'], true))
                        <p class="auth-hint">Local verification emails will use SMTP if you configure it. Otherwise the failover mailer will write them to <code>storage/logs/laravel.log</code>.</p>
                    @endif
                </div>
            @endif
        @else
            @if ($guestPreview !== null)
                <div class="usage-banner">
                    <div class="usage-copy">
                        <span class="eyebrow">Free guest draft used</span>
                        <h2>Your draft is ready to publish</h2>
                        <p>Create an account or sign in to publish or share this draft. It will count as your first free article once published.</p>
                    </div>
                </div>

                @include('posts.partials.guest-preview', [
                    'guestPreview' => $guestPreview,
                    'eyebrow' => 'Guest draft preview',
                    'accessNotice' => 'You can read this 1 article now. Create an account or sign in to share it and generate more articles.',
                    'showGuestAuthActions' => true,
                ])
            @elseif ($guestCanGeneratePreview)
                <div class="usage-banner is-highlighted">
                    <div class="usage-copy">
                        <span class="eyebrow">Guest trial</span>
                        <h2>{{ $guestFreeGenerationLimit }} free guest draft available</h2>
                        <p>Try BlogFuel before registering. Generate one full article draft now, then create an account to publish or share it and keep going.</p>
                    </div>
                </div>

                <div class="panel-header">
                    <div>
                        <span class="eyebrow">POST GENERATOR</span>
                        <h1 id="prompt-form">Build your next post:</h1>
                    </div>
                </div>

                @include('posts.partials.brief-form', [
                    'action' => route('posts.preview'),
                    'submitCopy' => 'Try one full article before registering. You will need an account to publish or share it.',
                    'submitLabel' => 'Generate free draft',
                    'submitButtonClass' => 'button-gradient-fire button-gradient-fire-light',
                    'inlineSubmitWithSeo' => true,
                    'lockedModel' => $guestTrialModel,
                ])
            @else
                <div class="guest-gate">
                    <h2>Your guest draft trial is already used.</h2>
                    <p>Register or sign in to publish articles, keep your draft history, and unlock the remaining free generations on an account.</p>
                </div>
            @endif
        @endauth
    </section>

    <section class="glass-panel side-panel" data-reveal>
        <div class="panel-header">
            <div>
                <span class="eyebrow">Example blog</span>
                <h2>Recent posts:</h2>
            </div>
        </div>

        @if ($posts->isNotEmpty())
            <div class="post-stack" data-home-post-grid>
                @include('posts.partials.home-post-rows', [
                    'posts' => $posts,
                ])
            </div>

            @if ($postsHasMore)
                <div class="load-more-panel">
                    <button
                        class="button load-more-button"
                        type="button"
                        data-home-load-more
                        data-home-url="{{ route('posts.recent') }}"
                        data-next-page="{{ $postsNextPage }}"
                    >
                        Load more
                    </button>
                    <small class="load-more-status" data-home-status aria-live="polite"></small>
                </div>
            @endif
        @else
            <div class="solo-state">
                <p>No articles yet. Generate your first one above and it will appear here immediately.</p>
            </div>
        @endif
    </section>
@endsection
