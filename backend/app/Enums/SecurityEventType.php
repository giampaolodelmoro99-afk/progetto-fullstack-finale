<?php

namespace App\Enums;

enum SecurityEventType: string
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case TwoFactorChallengeFailed = 'two_factor_challenge_failed';
    case Logout = 'logout';
    case EmailVerified = 'email_verified';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case EmailChangeRequested = 'email_change_requested';
    case EmailChanged = 'email_changed';
    case EmailChangeCancelled = 'email_change_cancelled';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';
    case RecoveryCodeUsed = 'recovery_code_used';
    case PasskeyAdded = 'passkey_added';
    case PasskeyRemoved = 'passkey_removed';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Accesso effettuato',
            self::LoginFailed => 'Accesso non riuscito',
            self::TwoFactorChallengeFailed => 'Verifica in due passaggi non riuscita',
            self::Logout => 'Disconnessione',
            self::EmailVerified => 'Email confermata',
            self::PasswordChanged => 'Password cambiata',
            self::PasswordReset => 'Password reimpostata',
            self::EmailChangeRequested => 'Cambio email richiesto',
            self::EmailChanged => 'Email aggiornata',
            self::EmailChangeCancelled => 'Cambio email annullato',
            self::TwoFactorEnabled => 'Doppia autenticazione attivata',
            self::TwoFactorDisabled => 'Doppia autenticazione disattivata',
            self::RecoveryCodesRegenerated => 'Codici di recupero rigenerati',
            self::RecoveryCodeUsed => 'Codice di recupero usato',
            self::PasskeyAdded => 'Passkey aggiunta',
            self::PasskeyRemoved => 'Passkey rimossa',
        };
    }
}
