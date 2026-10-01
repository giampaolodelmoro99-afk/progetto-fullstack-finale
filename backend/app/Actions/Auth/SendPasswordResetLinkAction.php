<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Sends the reset link when the address belongs to a user. The outcome of
 * the broker (unknown user, throttled) is ignored on purpose: the response
 * is always the same, so it never reveals whether the address is registered.
 * An anonymized account receives nothing, as defence in depth: its .invalid
 * address cannot receive mail anyway.
 */
final class SendPasswordResetLinkAction
{
    public function execute(string $email): void
    {
        if (User::query()->where('email', $email)->whereNotNull('anonymized_at')->exists()) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $email]);
    }
}
