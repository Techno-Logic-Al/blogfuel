@extends('layouts.app')
@inject('recaptcha', 'App\Services\RecaptchaEnterpriseVerifier')

@section('title', config('app.name').' | Register')
@section('meta_description', 'Create a BlogFuel account to start generating and publishing articles.')

@section('content')
    <section class="auth-shell" data-reveal>
        <div class="glass-panel form-panel auth-panel">
            <div class="panel-header">
                <div>
                    <span class="eyebrow">New account</span>
                    <h1>Register</h1>
                </div>
                <a class="text-link" href="{{ route('login') }}">Already registered?</a>
            </div>

            @if ($errors->any())
                <div class="alert-card alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                class="auth-form"
                method="POST"
                action="{{ route('register.store') }}"
                @if ($recaptcha->enabled())
                    data-recaptcha-form
                    data-recaptcha-action="REGISTER"
                @endif
            >
                @csrf

                <label class="field">
                    <span>Username</span>
                    <input
                        id="register_username"
                        name="username"
                        type="text"
                        value="{{ old('username') }}"
                        maxlength="30"
                        autocomplete="username"
                        data-username-input
                        data-username-check-url="{{ route('register.username.check') }}"
                        aria-describedby="register_username_status"
                        required
                    >
                    <small
                        id="register_username_status"
                        class="field-status @error('username') is-error @enderror"
                        data-username-status
                        role="status"
                        aria-live="polite"
                    >@error('username'){{ $message }}@enderror</small>
                </label>

                <label class="field">
                    <span>Email address</span>
                    <input
                        id="register_email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                    >
                    <small
                        class="field-status @error('email') is-error @enderror"
                    >@error('email'){{ $message }}@enderror</small>
                </label>

                <label class="field">
                    <span>Password</span>
                    <div class="password-input-wrap">
                        <input
                            id="register_password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            aria-describedby="register_password_status register_password_rules"
                            data-password-input
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle="register_password"
                            aria-controls="register_password"
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
                    <small
                        id="register_password_status"
                        class="field-status @error('password') is-error @enderror"
                        data-password-status
                        role="status"
                        aria-live="polite"
                    >@error('password'){{ $message }}@else Use at least 8 characters with an uppercase letter, number, and symbol.@enderror</small>
                    <ul
                        id="register_password_rules"
                        class="password-rule-list"
                        data-password-rules
                    >
                        <li class="password-rule" data-password-rule="length">At least 8 characters</li>
                        <li class="password-rule" data-password-rule="uppercase">At least 1 uppercase letter</li>
                        <li class="password-rule" data-password-rule="number">At least 1 number</li>
                        <li class="password-rule" data-password-rule="symbol">At least 1 special symbol</li>
                    </ul>
                </label>

                <label class="field">
                    <span>Confirm password</span>
                    <div class="password-input-wrap">
                        <input
                            id="register_password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle="register_password_confirmation"
                            aria-controls="register_password_confirmation"
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

                <div class="form-actions">
                    <p>Create an account, verify your email, and then start generating and publishing articles.</p>
                    <button class="button button-gradient-fire button-gradient-fire-light" type="submit">Register</button>
                </div>

                @if ($recaptcha->enabled())
                    @include('partials.recaptcha-fields')
                @endif
            </form>
        </div>
    </section>
@endsection
