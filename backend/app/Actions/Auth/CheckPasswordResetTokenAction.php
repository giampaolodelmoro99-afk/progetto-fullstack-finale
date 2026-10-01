<?php

namespace App\Actions\Auth;

use App\Exceptions\Auth\InvalidPasswordResetTokenException;
use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Returns the user of a valid, unexpired reset token. A wrong token, an
 * expired one and an unknown email give the same exception.
 */
final class CheckPasswordResetTokenAction
{
    public function execute(string $email, #[\SensitiveParameter] string $token): User
    {
        $broker = Password::broker();
        $user = $broker->getUser(['email' => $email]);

        if (! $user instanceof User || ! $broker->tokenExists($user, $token)) {
            throw new InvalidPasswordResetTokenException;
        }

        return $user;
    }
}
