<?php

namespace App\Enums;

/**
 * Operation that asked for the second factor, saved in the metadata of a
 * failed verification.
 */
enum SecondFactorContext: string
{
    case Login = 'login';
    case PasswordReset = 'password_reset';
    case IdentityConfirmation = 'identity_confirmation';
    case PasswordChange = 'password_change';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Accesso',
            self::PasswordReset => 'Recupero password',
            self::IdentityConfirmation => 'Conferma dell\'identità',
            self::PasswordChange => 'Cambio password',
        };
    }
}
