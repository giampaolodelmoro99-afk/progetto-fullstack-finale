<?php

namespace App\Actions\Security;

use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;
use App\Http\Middleware\EnsureIdentityConfirmed;
use App\Models\User;
use App\Support\Auth\IdentityConfirmation;
use Illuminate\Http\Request;

/**
 * Step-up before a sensitive operation. The password is checked by the Form
 * Request (current_password); with two-factor authentication the second
 * factor is required too. The confirmation is stored in the session and
 * lasts confirmation_timeout seconds.
 */
final class ConfirmIdentityAction
{
    public function __construct(
        private readonly Request $request,
        private readonly VerifySecondFactorAction $verifySecondFactor,
    ) {}

    public function execute(User $user, ?SecondFactorMethod $method, #[\SensitiveParameter] ?string $code): IdentityConfirmation
    {
        $secondFactor = $user->hasSecondFactor()
            ? $this->verifySecondFactor->execute($user, $method, ['code' => $code], SecondFactorContext::IdentityConfirmation)
            : null;

        $confirmedAt = now();
        $this->request->session()->put(EnsureIdentityConfirmed::SESSION_KEY, $confirmedAt->getTimestamp());

        return new IdentityConfirmation(
            $confirmedAt->copy()->addSeconds(config('security.confirmation_timeout')),
            $secondFactor,
        );
    }
}
