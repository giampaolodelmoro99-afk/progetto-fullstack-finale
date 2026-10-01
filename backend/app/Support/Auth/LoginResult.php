<?php

namespace App\Support\Auth;

use App\Models\User;

/**
 * Outcome of a password login: the authenticated user, or the second
 * factor methods of a pending challenge.
 */
final readonly class LoginResult
{
    /**
     * @param  list<string>  $methods
     */
    private function __construct(
        public ?User $user,
        public array $methods,
    ) {}

    public static function authenticated(User $user): self
    {
        return new self($user, []);
    }

    /**
     * @param  list<string>  $methods
     */
    public static function twoFactorRequired(array $methods): self
    {
        return new self(null, $methods);
    }

    public function requiresTwoFactor(): bool
    {
        return $this->user === null;
    }
}
