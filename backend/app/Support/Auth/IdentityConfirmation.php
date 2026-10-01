<?php

namespace App\Support\Auth;

use Carbon\CarbonInterface;

/**
 * Outcome of an identity confirmation: when it stops being valid, and the
 * second factor used, if two-factor authentication is active.
 */
final readonly class IdentityConfirmation
{
    public function __construct(
        public CarbonInterface $confirmedUntil,
        public ?SecondFactorResult $secondFactor,
    ) {}
}
