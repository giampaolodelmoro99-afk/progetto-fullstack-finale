<?php

namespace App\Support\Auth;

use App\Enums\SecondFactorMethod;

/**
 * Outcome of a successful second factor. After a recovery code the response
 * tells how many codes are left, so the frontend can invite the user to
 * regenerate them when LOW_RECOVERY_CODES or fewer remain.
 */
final readonly class SecondFactorResult
{
    public const LOW_RECOVERY_CODES = 2;

    public function __construct(
        public SecondFactorMethod $method,
        public int $recoveryCodesRemaining,
    ) {}

    public function recoveryCodesLow(): bool
    {
        return $this->recoveryCodesRemaining <= self::LOW_RECOVERY_CODES;
    }

    /**
     * The meta of the response: present only after a recovery code.
     *
     * @return array<string, array<string, bool|int>>
     */
    public function meta(): array
    {
        if ($this->method !== SecondFactorMethod::RecoveryCode) {
            return [];
        }

        return [
            'meta' => [
                'recovery_codes_remaining' => $this->recoveryCodesRemaining,
                'recovery_codes_low' => $this->recoveryCodesLow(),
            ],
        ];
    }
}
