<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            $routeName = $request->route()?->getName();
            $isGenerationRoute = in_array($routeName, ['posts.preview', 'posts.store', 'posts.preview.publish'], true)
                || $request->is('trial')
                || ($request->is('studio') && $request->isMethod('post'))
                || $request->is('trial/publish');

            if (
                $request->expectsJson()
                || ! $isGenerationRoute
            ) {
                return null;
            }

            $target = url()->previous();

            if ($target === '' || $target === $request->fullUrl()) {
                $target = route('posts.index');
            }

            return redirect()
                ->to($target)
                ->withInput()
                ->withErrors([
                    'generation' => 'Too many generation attempts right now. Please wait a few minutes and try again.',
                ]);
        });
    })->create();
