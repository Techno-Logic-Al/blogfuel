<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>BlogFuel Deployment</title>
        @vite(['resources/css/app.css'])
    </head>
    <body>
        <div class="page-frame">
            <main class="page-content">
                @if (session('status'))
                    <div class="flash-card">
                        <p>{{ session('status') }}</p>
                    </div>
                @endif

                <section class="auth-shell">
                    <div class="glass-panel form-panel auth-panel">
                        <div class="panel-header">
                            <div>
                                <span class="eyebrow">One-time deployment helper</span>
                                <h1>Browser deployment setup</h1>
                            </div>
                        </div>

                        <div class="guest-gate">
                            <p>This screen clears Laravel caches, runs the database migrations, and seeds the admin account. Use it once during deployment, then disable it in <code>.env</code>.</p>
                            <p><strong>Commands:</strong> <code>config:clear</code>, <code>route:clear</code>, <code>view:clear</code>, <code>migrate --force</code>, <code>db:seed --force</code></p>
                        </div>

                        @if ($error)
                            <div class="alert-card alert-error">
                                {{ $error }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('deployment.run', ['token' => $token]) }}" class="auth-form">
                            @csrf

                            <div class="form-actions">
                                <p>Run this only after the live <code>.env</code> file is complete and the subdomain document root points at <code>public/</code>.</p>
                                <button class="button button-primary" type="submit">Run deployment setup</button>
                            </div>
                        </form>

                        @if ($results !== [])
                            <div class="deployment-results">
                                @foreach ($results as $result)
                                    <div class="quote-card deployment-result">
                                        <strong>{{ $result['command'] }}</strong>
                                        <pre>{{ $result['output'] }}</pre>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
