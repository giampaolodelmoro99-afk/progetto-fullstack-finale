<?php

namespace App\Contracts\Security;

use App\Models\User;

/**
 * Checks one kind of second factor. A successful verification consumes the
 * factor on the given user and saves it (last TOTP interval, used recovery
 * code), so the same factor never works twice.
 */
interface SecondFactorVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(User $user, #[\SensitiveParameter] array $payload): bool;
}
