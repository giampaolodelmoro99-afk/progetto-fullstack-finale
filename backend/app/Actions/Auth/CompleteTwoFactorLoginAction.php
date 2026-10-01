<?php

namespace App\Actions\Auth;

use App\Actions\Security\RecordSecurityEventAction;
use App\Actions\Security\VerifySecondFactorAction;
use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;
use App\Enums\SecurityEventType;
use App\Exceptions\Auth\TwoFactorChallengeExpiredException;
use App\Http\Middleware\EnsureIdentityConfirmed;
use App\Models\User;
use App\Support\Auth\TwoFactorLoginResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Second step of a password login with two-factor authentication. The
 * challenge stored by LoginAction lasts login_challenge_ttl seconds: when
 * it is missing or expired, or its user no longer has two-factor
 * authentication, it is dropped and the frontend goes back to the login.
 *
 * A wrong code keeps the challenge, so the user can try again within the
 * two-factor limiter; a right one authenticates and regenerates the session.
 */
final class CompleteTwoFactorLoginAction
{
    public function __construct(
        private readonly Request $request,
        private readonly VerifySecondFactorAction $verifySecondFactor,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(SecondFactorMethod $method, #[\SensitiveParameter] string $code): TwoFactorLoginResult
    {
        $user = $this->pendingUser();

        $secondFactor = $this->verifySecondFactor->execute($user, $method, ['code' => $code], SecondFactorContext::Login);

        $session = $this->request->session();
        $session->forget([LoginAction::PENDING_USER_KEY, LoginAction::PENDING_AT_KEY, EnsureIdentityConfirmed::SESSION_KEY]);
        Auth::guard('web')->login($user);
        $session->regenerate();

        $this->recordSecurityEvent->execute($user, SecurityEventType::Login);

        return new TwoFactorLoginResult($user, $secondFactor);
    }

    private function pendingUser(): User
    {
        $session = $this->request->session();
        $userId = $session->get(LoginAction::PENDING_USER_KEY);
        $startedAt = $session->get(LoginAction::PENDING_AT_KEY);

        $user = is_int($userId) && is_int($startedAt)
            && now()->getTimestamp() - $startedAt <= config('security.login_challenge_ttl')
                ? User::query()->find($userId)
                : null;

        if ($user === null || ! $user->hasSecondFactor()) {
            $session->forget([LoginAction::PENDING_USER_KEY, LoginAction::PENDING_AT_KEY]);

            throw new TwoFactorChallengeExpiredException;
        }

        return $user;
    }
}
