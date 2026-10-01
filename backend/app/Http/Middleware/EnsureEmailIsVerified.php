<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias verified. Replaces the Laravel middleware, which answers with an
 * English message, with a JSON 403 in Italian.
 */
final class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())) {
            return response()->json([
                'message' => 'Conferma il tuo indirizzo email per continuare.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
