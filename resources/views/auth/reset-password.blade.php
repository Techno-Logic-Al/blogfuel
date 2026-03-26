@extends('layouts.app')

@section('title', config('app.name').' | Reset Password')
@section('meta_description', 'Choose a new password for your BlogFuel account.')

@section('content')
    <section class="auth-shell" data-reveal>
        <div class="glass-panel form-panel auth-panel">
            <div class="panel-header">
                <div>
                    <span class="eyebrow">Choose a new password</span>
                    <h1>Reset password</h1>
                </div>
                <a class="text-link" href="{{ route('login') }}">Back to login</a>
            </div>

            @if ($errors->any())
                <div class="alert-card alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="guest-gate">
                <p>Use this secure form to set a new password for your BlogFuel account and sign in again.</p>
            </div>

            <form class="auth-form" method="POST" action="{{ route('password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <label class="field">
                    <span>Email address</span>
                    <input
                        id="reset_password_email"
                        name="email"
                        type="email"
                        value="{{ old('email', $email) }}"
                        autocomplete="email"
                        required
                    >
                    <small class="field-status @error('email') is-error @enderror">
                        @error('email'){{ $message }}@enderror
                    </small>
                </label>

                <label class="field">
                    <span>New password</span>
                    <div class="password-input-wrap">
                        <input
                            id="reset_password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            aria-describedby="reset_password_status reset_password_rules"
                            data-password-input
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle="reset_password"
                            aria-controls="reset_password"
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
                        id="reset_password_status"
                        class="field-status @error('password') is-error @enderror"
                        data-password-status
                        role="status"
                        aria-live="polite"
                    >@error('password'){{ $message }}@else Use at least 8 characters with an uppercase letter, number, and symbol.@enderror</small>
                    <ul
                        id="reset_password_rules"
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
                    <span>Confirm new password</span>
                    <div class="password-input-wrap">
                        <input
                            id="reset_password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle="reset_password_confirmation"
                            aria-controls="reset_password_confirmation"
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
                    <p>Set a fresh password, then sign in again with your updated details.</p>
                    <button class="button button-primary" type="submit">Reset password</button>
                </div>
            </form>
        </div>
    </section>
@endsection
