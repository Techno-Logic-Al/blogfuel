<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class DeploymentController extends Controller
{
    /**
     * Show the one-time browser deployment screen.
     */
    public function show(Request $request): View
    {
        $this->guardAccess($request);

        return view('deployment.setup', [
            'token' => (string) $request->query('token', ''),
            'results' => session('deployment.results', []),
            'error' => session('deployment.error'),
        ]);
    }

    /**
     * Run the browser deployment commands once the token is confirmed.
     */
    public function run(Request $request): RedirectResponse
    {
        $this->guardAccess($request);

        $results = [];

        try {
            $results[] = $this->runCommand('config:clear');
            $results[] = $this->runCommand('route:clear');
            $results[] = $this->runCommand('view:clear');
            $results[] = $this->runCommand('migrate', ['--force' => true]);
            $results[] = $this->runCommand('db:seed', ['--force' => true]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('deployment.setup', ['token' => (string) $request->query('token', '')])
                ->with('deployment.results', $results)
                ->with('deployment.error', $exception->getMessage());
        }

        return redirect()
            ->route('deployment.setup', ['token' => (string) $request->query('token', '')])
            ->with('deployment.results', $results)
            ->with('status', 'Deployment commands completed successfully.');
    }

    /**
     * Ensure the deployment route is enabled and the token matches.
     */
    protected function guardAccess(Request $request): void
    {
        $enabled = (bool) config('deployment.enabled', false);
        $expectedToken = trim((string) config('deployment.token', ''));
        $providedToken = trim((string) $request->query('token', ''));

        if (! $enabled || $expectedToken === '' || $providedToken === '') {
            throw new NotFoundHttpException();
        }

        if (! hash_equals($expectedToken, $providedToken)) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * Run an Artisan command and capture its output for the browser.
     *
     * @param  array<string, bool|string|int>  $arguments
     * @return array{command: string, output: string}
     */
    protected function runCommand(string $command, array $arguments = []): array
    {
        Artisan::call($command, $arguments);

        $argumentPreview = collect($arguments)
            ->map(function (mixed $value, string $key): string {
                if ($value === true) {
                    return $key;
                }

                return sprintf('%s=%s', $key, (string) $value);
            })
            ->implode(' ');

        return [
            'command' => trim($command.' '.$argumentPreview),
            'output' => trim(Artisan::output()) ?: 'Completed with no console output.',
        ];
    }
}
