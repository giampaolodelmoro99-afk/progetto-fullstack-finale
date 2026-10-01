<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias role, used as role:admin. First barrier of the admin area: the
 * Policies still check every permission.
 */
final class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role): UserRole => UserRole::from($role), $roles);

        if (! in_array($request->user()?->role, $allowed, true)) {
            return response()->json([
                'message' => 'Non hai i permessi per questa operazione.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
