<?php

namespace App\Enums;

/**
 * Purpose of the options of a passkey ceremony. Each purpose keeps its own
 * options in the session, so the options of one operation never complete
 * another: those of the passwordless login do not confirm the identity.
 */
enum PasskeyPurpose: string
{
    case Registration = 'registration';
    case Login = 'login';
    case SecondFactorLogin = 'second_factor_login';
    case PasswordReset = 'password_reset';
    case IdentityConfirmation = 'identity_confirmation';
    case PasswordChange = 'password_change';

    public static function forSecondFactor(SecondFactorContext $context): self
    {
        return match ($context) {
            SecondFactorContext::Login => self::SecondFactorLogin,
            SecondFactorContext::PasswordReset => self::PasswordReset,
            SecondFactorContext::IdentityConfirmation => self::IdentityConfirmation,
            SecondFactorContext::PasswordChange => self::PasswordChange,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registrazione della passkey',
            self::Login => 'Accesso con passkey',
            self::SecondFactorLogin => 'Verifica in due passaggi all\'accesso',
            self::PasswordReset => 'Recupero password',
            self::IdentityConfirmation => 'Conferma dell\'identità',
            self::PasswordChange => 'Cambio password',
        };
    }
}
