<?php

namespace App\Services\Security;

use App\Actions\Auth\LoginAction;
use App\Exceptions\Auth\TwoFactorChallengeExpiredException;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The challenge stored by LoginAction after the password of a user with
 * two-factor authentication. It lasts login_challenge_ttl seconds: when it
 * is missing or expired, or its user no longer has two-factor
 * authentication, it is dropped and the frontend goes back to the login.
 * Used by the second step of the login and by its passkey options.
 */
final class PendingLoginChallenge
{
    public function __construct(private readonly Request $request) {}

    public function user(): User
    {
        $session = $this->request->session();
        $userId = $session->get(LoginAction::PENDING_USER_KEY);
        $startedAt = $session->get(LoginAction::PENDING_AT_KEY);

        $user = is_int($userId) && is_int($startedAt)
            && now()->getTimestamp() - $startedAt <= config('security.login_challenge_ttl')
                ? User::query()->find($userId)
                : null;

        if ($user === null || ! $user->hasSecondFactor()) {
            $this->forget();

            throw new TwoFactorChallengeExpiredException;
        }

        return $user;
    }

    public function forget(): void
    {
        $this->request->session()->forget([LoginAction::PENDING_USER_KEY, LoginAction::PENDING_AT_KEY]);
    }
}
