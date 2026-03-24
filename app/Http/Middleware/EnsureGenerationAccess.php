<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGenerationAccess
{
    /**
     * Ensure the signed-in user is still allowed to generate articles.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->canGeneratePosts()) {
            return $next($request);
        }

        return new RedirectResponse(route('posts.index'));
    }
}
