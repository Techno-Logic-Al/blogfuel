@extends('layouts.app')
@inject('recaptcha', 'App\Services\RecaptchaEnterpriseVerifier')

@section('title', config('app.name').' | Forgot Password')
@section('meta_description', 'Request a secure password reset link for your BlogFuel account.')

@section('content')
    <section class="auth-shell" data-reveal>
        <div class="glass-panel form-panel auth-panel">
            <div class="panel-header">
                <div>
                    <span class="eyebrow">Password reset</span>
                    <h1>Forgot your password?</h1>
                </div>
                <a class="text-link" href="{{ route('login') }}">Back to login</a>
            </div>

            @if ($errors->any())
                <div class="alert-card alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="guest-gate">
                <p>Enter the email address for your BlogFuel account and we will send you a secure link to choose a new password.</p>
            </div>

            <form
                class="auth-form"
                method="POST"
                action="{{ route('password.email') }}"
                @if ($recaptcha->enabled())
                    data-recaptcha-form
                    data-recaptcha-action="PASSWORD_RESET_REQUEST"
                @endif
            >
                @csrf

                <label class="field">
                    <span>Email address</span>
                    <input
                        id="forgot_password_email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                    >
                    <small class="field-status @error('email') is-error @enderror">
                        @error('email'){{ $message }}@enderror
                    </small>
                </label>

                <div class="form-actions">
                    <p>We will email a one-time reset link that lets you choose a new password for this account.</p>
                    <button class="button button-primary" type="submit">Send reset email</button>
                </div>

                @if ($recaptcha->enabled())
                    @include('partials.recaptcha-fields')
                @endif
            </form>
        </div>
    </section>
@endsection
