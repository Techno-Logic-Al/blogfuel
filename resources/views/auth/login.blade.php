@extends('layouts.app')
@inject('recaptcha', 'App\Services\RecaptchaEnterpriseVerifier')

@section('title', config('app.name').' | Login')
@section('meta_description', 'Sign in to your BlogFuel account to generate and publish articles.')

@section('content')
    <section class="auth-shell" data-reveal>
        <div class="glass-panel form-panel auth-panel">
            <div class="panel-header">
                <div>
                    <span class="eyebrow">Account access</span>
                    <h1>Login</h1>
                </div>
                <a class="text-link" href="{{ route('register') }}">Create an account</a>
            </div>

            @if ($errors->any())
                <div class="alert-card alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                class="auth-form"
                method="POST"
                action="{{ route('login.attempt') }}"
                @if ($recaptcha->enabled())
                    data-recaptcha-form
                    data-recaptcha-action="LOGIN"
                @endif
            >
                @csrf

                <label class="field">
                    <span>Username</span>
                    <input
                        name="username"
                        type="text"
                        value="{{ old('username') }}"
                        maxlength="30"
                        autocomplete="username"
                        required
                    >
                </label>

                <label class="field">
                    <span>Password</span>
                    <div class="password-input-wrap">
                        <input
                            id="login_password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle="login_password"
                            aria-controls="login_password"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <span class="password-toggle-icon icon-show" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </span>
                            <span class="password-toggle-icon icon-hide" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="presentation">
                                    <path d="M3 4l18 16" />
                                    <path d="M10.6 6.3A11.5 11.5 0 0112 6c6.4 0 10 6 10 6a19 19 0 01-3.2 3.8" />
                                    <path d="M6.7 8.2A18.3 18.3 0 002 12s3.6 6 10 6c1.8 0 3.4-.4 4.9-1.1" />
                                    <path d="M9.9 9.8A3 3 0 0115 12a3 3 0 01-.4 1.5" />
                                </svg>
                            </span>
                        </button>
                    </div>
                </label>

                <a class="text-link" href="{{ route('password.request') }}">Forgot your password?</a>

                <div class="form-actions">
                    <p>Sign in to start generating articles from the homepage.</p>
                    <button class="button button-primary" type="submit">Login</button>
                </div>

                @if ($recaptcha->enabled())
                    @include('partials.recaptcha-fields')
                @endif
            </form>
        </div>
    </section>
@endsection
