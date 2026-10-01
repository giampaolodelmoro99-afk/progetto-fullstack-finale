<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias identity.confirmed. A sensitive operation needs an identity
 * confirmation younger than confirmation_timeout: otherwise 423, and the
 * frontend opens the confirmation dialog and repeats the request. requires
 * lists second_factor only when two-factor authentication is active.
 *
 * The confirmation lives in the session, so the logout clears it; it runs
 * after the permission checks (priority list in bootstrap/app.php), so a
 * forbidden operation answers 403 without asking for the password.
 */
final class EnsureIdentityConfirmed
{
    public const SESSION_KEY = 'auth.identity_confirmed_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (self::isConfirmed($request)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Conferma la tua identità per continuare.',
            'requires' => $request->user()->hasSecondFactor() ? ['password', 'second_factor'] : ['password'],
        ], Response::HTTP_LOCKED);
    }

    private static function isConfirmed(Request $request): bool
    {
        $confirmedAt = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return is_int($confirmedAt)
            && now()->getTimestamp() - $confirmedAt <= config('security.confirmation_timeout');
    }
}
