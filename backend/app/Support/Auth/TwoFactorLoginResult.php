<?php

namespace App\Support\Auth;

use App\Models\User;

/**
 * Outcome of a completed login challenge: the authenticated user and the
 * second factor used.
 */
final readonly class TwoFactorLoginResult
{
    public function __construct(
        public User $user,
        public SecondFactorResult $secondFactor,
    ) {}
}
