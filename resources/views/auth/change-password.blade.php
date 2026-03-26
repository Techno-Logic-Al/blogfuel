@extends('layouts.app')

@section('title', config('app.name').' | Change Password')
@section('meta_description', 'Change the password for your BlogFuel account.')

@section('content')
    <section class="auth-shell" data-reveal>
        <div class="glass-panel form-panel auth-panel">
            <div class="panel-header">
                <div>
                    <span class="eyebrow">Account security</span>
                    <h1>Change password</h1>
                </div>
                <a class="text-link" href="{{ route('posts.index') }}">Back to homepage</a>
            </div>

            @if ($errors->any())
                <div class="alert-card alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="guest-gate">
                <p>Enter your current password, then choose a new one for your BlogFuel account.</p>
            </div>

            <form class="auth-form" method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')

                <label class="field">
                    <span>Current password</span>
                    <div class="password-input-wrap">
                        <input
                            id="current_password"
                            name="current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle="current_password"
                            aria-controls="current_password"
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
                    <small class="field-status @error('current_password') is-error @enderror">
                        @error('current_password'){{ $message }}@enderror
                    </small>
                </label>

                <label class="field">
                    <span>New password</span>
                    <div class="password-input-wrap">
                        <input
                            id="change_password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            aria-describedby="change_password_status change_password_rules"
                            data-password-input
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle="change_password"
                            aria-controls="change_password"
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
                        id="change_password_status"
                        class="field-status @error('password') is-error @enderror"
                        data-password-status
                        role="status"
                        aria-live="polite"
                    >@error('password'){{ $message }}@else Use at least 8 characters with an uppercase letter, number, and symbol.@enderror</small>
                    <ul
                        id="change_password_rules"
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
                            id="change_password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle="change_password_confirmation"
                            aria-controls="change_password_confirmation"
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
                    <p>Your current password is required before the new one can be saved.</p>
                    <button class="button button-primary" type="submit">Update password</button>
                </div>
            </form>
        </div>
    </section>
@endsection
