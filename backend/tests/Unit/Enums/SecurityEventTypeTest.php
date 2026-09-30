<?php

use App\Enums\SecurityEventType;

it('has the fixed italian labels', function (string $value, string $label) {
    expect(SecurityEventType::from($value)->label())->toBe($label);
})->with([
    ['login', 'Accesso effettuato'],
    ['login_failed', 'Accesso non riuscito'],
    ['two_factor_challenge_failed', 'Verifica in due passaggi non riuscita'],
    ['logout', 'Disconnessione'],
    ['email_verified', 'Email confermata'],
    ['password_changed', 'Password cambiata'],
    ['password_reset', 'Password reimpostata'],
    ['email_change_requested', 'Cambio email richiesto'],
    ['email_changed', 'Email aggiornata'],
    ['email_change_cancelled', 'Cambio email annullato'],
    ['two_factor_enabled', 'Doppia autenticazione attivata'],
    ['two_factor_disabled', 'Doppia autenticazione disattivata'],
    ['recovery_codes_regenerated', 'Codici di recupero rigenerati'],
    ['recovery_code_used', 'Codice di recupero usato'],
    ['passkey_added', 'Passkey aggiunta'],
    ['passkey_removed', 'Passkey rimossa'],
    ['other_sessions_closed', 'Altre sessioni chiuse'],
]);

it('has exactly seventeen cases', function () {
    expect(SecurityEventType::cases())->toHaveCount(17);
});

it('fits every value in the 50 characters of the type column', function () {
    foreach (SecurityEventType::cases() as $type) {
        expect(strlen($type->value))->toBeLessThanOrEqual(50);
    }
});
