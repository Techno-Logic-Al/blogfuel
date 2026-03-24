@extends('layouts.app')
@inject('recaptcha', 'App\Services\RecaptchaEnterpriseVerifier')

@section('title', config('app.name').' | Verify Email')
@section('meta_description', 'Verify your email address to unlock article generation in BlogFuel.')

@section('content')
    <section class="auth-shell" data-reveal>
        <div class="glass-panel form-panel auth-panel">
            <div class="panel-header">
                <div>
                    <span class="eyebrow">Email verification</span>
                    <h1>Verify your email</h1>
                </div>
                <a class="text-link" href="{{ route('posts.index') }}">Back to homepage</a>
            </div>

            @if ($errors->any())
                <div class="alert-card alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="guest-gate">
                <p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Open that email to confirm your address, then come back here to start generating and publishing articles.</p>
                <p>If the link expires or you cannot find the message, request a fresh verification email below.</p>

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

                @if (app()->isLocal() && in_array(config('mail.default'), ['log', 'failover'], true))
                    <p class="auth-hint">Local verification emails will use SMTP if you configure it. Otherwise the failover mailer will write them to <code>storage/logs/laravel.log</code>.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
