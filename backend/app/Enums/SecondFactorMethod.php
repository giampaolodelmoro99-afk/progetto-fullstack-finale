<?php

namespace App\Enums;

/**
 * Second factor accepted when two-factor authentication is active. The
 * passkey joins in Phase 3c, together with its verifier.
 */
enum SecondFactorMethod: string
{
    case Totp = 'totp';
    case RecoveryCode = 'recovery_code';

    public function label(): string
    {
        return match ($this) {
            self::Totp => 'App di autenticazione',
            self::RecoveryCode => 'Codice di recupero',
        };
    }
}
