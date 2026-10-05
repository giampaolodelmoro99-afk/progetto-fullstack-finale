<?php

namespace App\Actions\Auth;

use App\Actions\Security\RecordSecurityEventAction;
use App\Actions\Security\VerifySecondFactorAction;
use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;
use App\Enums\SecurityEventType;
use App\Http\Middleware\EnsureIdentityConfirmed;
use App\Services\Security\PendingLoginChallenge;
use App\Support\Auth\TwoFactorLoginResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Second step of a password login with two-factor authentication: code of
 * the authenticator app, recovery code or passkey. The challenge is checked
 * by PendingLoginChallenge.
 *
 * A wrong code keeps the challenge, so the user can try again within the
 * two-factor limiter; a right one authenticates and regenerates the session.
 * After a passkey the login event names it.
 */
final class CompleteTwoFactorLoginAction
{
    public function __construct(
        private readonly Request $request,
        private readonly PendingLoginChallenge $pendingLogin,
        private readonly VerifySecondFactorAction $verifySecondFactor,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @param  array{code?: string|null, credential?: array<string, mixed>|null}  $payload
     */
    public function execute(SecondFactorMethod $method, #[\SensitiveParameter] array $payload): TwoFactorLoginResult
    {
        $user = $this->pendingLogin->user();

        $secondFactor = $this->verifySecondFactor->execute($user, $method, $payload, SecondFactorContext::Login);

        $this->pendingLogin->forget();
        $session = $this->request->session();
        $session->forget(EnsureIdentityConfirmed::SESSION_KEY);
        Auth::guard('web')->login($user);
        $session->regenerate();

        $this->recordSecurityEvent->execute($user, SecurityEventType::Login, $secondFactor->loginEventMetadata());

        return new TwoFactorLoginResult($user, $secondFactor);
    }
}
