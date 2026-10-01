<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

/**
 * Sends the reset link when the address belongs to a user. The outcome of
 * the broker (unknown user, throttled) is ignored on purpose: the response
 * is always the same, so it never reveals whether the address is registered.
 */
final class SendPasswordResetLinkAction
{
    public function execute(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }
}
