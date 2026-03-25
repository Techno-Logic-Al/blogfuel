<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @inject('recaptcha', 'App\Services\RecaptchaEnterpriseVerifier')
        @php
            $metaTitle = trim($__env->yieldContent('meta_title', config('app.name')));
            $documentTitle = trim($__env->yieldContent('title', config('app.name')));
            $metaDescription = trim($__env->yieldContent('meta_description', 'AI-assisted Laravel blog with prompt-based post generation.'));
            $metaType = trim($__env->yieldContent('meta_type', 'website'));
            $metaUrl = trim($__env->yieldContent('meta_url', url()->current()));
            $metaImage = trim($__env->yieldContent('meta_image', asset('images/BlogFuel round totally transparent icon.png')));
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $metaDescription }}">
        <meta property="og:type" content="{{ $metaType }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ $metaUrl }}">
        <meta property="og:image" content="{{ $metaImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $metaImage }}">
        <link rel="canonical" href="{{ $metaUrl }}">

        <title>{{ $documentTitle }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|poppins:700,800" rel="stylesheet" />
        <link rel="icon" type="image/png" href="{{ asset('images/BlogFuel round totally transparent icon.png') }}">

        @if ($recaptcha->enabled() && $recaptcha->siteKey() !== null)
            <script src="https://www.google.com/recaptcha/enterprise.js?render={{ $recaptcha->siteKey() }}"></script>
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body @if ($recaptcha->enabled() && $recaptcha->siteKey() !== null) data-recaptcha-site-key="{{ $recaptcha->siteKey() }}" @endif>
        <div class="page-frame" id="top">
            <div class="ambient ambient-one" aria-hidden="true"></div>
            <div class="ambient ambient-two" aria-hidden="true"></div>
            <div class="ambient ambient-three" aria-hidden="true"></div>

            <header class="site-header" data-reveal>
                <div class="brand-lockup">
                    <a class="brand" href="{{ route('posts.index') }}">
                        <img class="brand-logo" src="{{ asset('images/BlogFuel oval transparent logo.png') }}" alt="BlogFuel">
                    </a>
                    <div class="site-tagline">
                        <img
                            class="site-tagline-image"
                            src="{{ asset('images/Header tagline yellow orange red.png') }}"
                            alt="Generate and share AI blog posts on any topic."
                        >
                    </div>
                </div>

                @auth
                    <div class="header-actions">
                        <span class="user-pill">Signed in as <strong>{{ auth()->user()->name }}</strong></span>
                        @if (auth()->user()->hasVerifiedEmail())
                            @if (auth()->user()->hasUnlimitedGenerationAccess())
                                <span class="status-pill">Unlimited free posts</span>
                            @elseif (auth()->user()->hasActiveSubscription() && auth()->user()->canOpenBillingPortal())
                                <form method="POST" action="{{ route('billing.portal') }}">
                                    @csrf
                                    <button class="button button-secondary" type="submit">Manage billing</button>
                                </form>
                            @elseif (auth()->user()->shouldConsumeCreditForNextGeneration())
                                <span class="status-pill">{{ auth()->user()->creditBalance() }} paid posts left</span>
                            @elseif (auth()->user()->hasReachedGenerationLimit())
                                <a class="button button-primary" href="{{ route('posts.index') }}#plans">Choose plan</a>
                            @else
                                <span class="status-pill">{{ auth()->user()->freeGenerationsRemaining() }} free posts left</span>
                            @endif
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="button header-logout-button" type="submit">Logout</button>
                        </form>
                    </div>
                @else
                    <div class="header-actions header-actions-guest">
                        <a class="button button-ember" href="{{ route('login') }}">Login</a>
                        <a class="button button-gradient-fire button-gradient-fire-light" href="{{ route('register') }}">Register</a>
                    </div>
                @endauth
            </header>

            <main class="page-content">
                @if (session('status'))
                    <div class="flash-card" data-reveal>
                        <p>{{ session('status') }}</p>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="site-footer">
                <a class="footer-brand" href="{{ route('posts.index') }}">
                    <img class="footer-brand-image" src="{{ asset('images/BlogFuel text only transparent.png') }}" alt="BlogFuel">
                </a>

                <div class="footer-copy">
                    <p>Designed and created by Techno-Logic-Al Web Studio.</p>
                    <p>ChatGPT is a registered trademark of OpenAI.</p>
                </div>

                <a class="footer-top-link" href="#top">Back to top</a>
            </footer>
        </div>
    </body>
</html>
