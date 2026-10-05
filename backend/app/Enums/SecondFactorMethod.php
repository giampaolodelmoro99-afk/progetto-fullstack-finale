<?php

namespace App\Enums;

/**
 * Second factor accepted when two-factor authentication is active. The
 * passkey is offered only to a user who has registered one.
 */
enum SecondFactorMethod: string
{
    case Totp = 'totp';
    case RecoveryCode = 'recovery_code';
    case Passkey = 'passkey';

    public function label(): string
    {
        return match ($this) {
            self::Totp => 'App di autenticazione',
            self::RecoveryCode => 'Codice di recupero',
            self::Passkey => 'Passkey',
        };
    }
}
