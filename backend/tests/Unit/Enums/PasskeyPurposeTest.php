<?php

use App\Enums\PasskeyPurpose;
use App\Enums\SecondFactorContext;

it('labels the purposes of the passkey options in italian', function (PasskeyPurpose $purpose, string $value, string $label) {
    expect($purpose->value)->toBe($value)
        ->and($purpose->label())->toBe($label);
})->with([
    [PasskeyPurpose::Registration, 'registration', 'Registrazione della passkey'],
    [PasskeyPurpose::Login, 'login', 'Accesso con passkey'],
    [PasskeyPurpose::SecondFactorLogin, 'second_factor_login', 'Verifica in due passaggi all\'accesso'],
    [PasskeyPurpose::PasswordReset, 'password_reset', 'Recupero password'],
    [PasskeyPurpose::IdentityConfirmation, 'identity_confirmation', 'Conferma dell\'identità'],
    [PasskeyPurpose::PasswordChange, 'password_change', 'Cambio password'],
]);

it('keeps the options of every second factor context apart from the passwordless login', function (SecondFactorContext $context, PasskeyPurpose $purpose) {
    expect(PasskeyPurpose::forSecondFactor($context))->toBe($purpose);
})->with([
    [SecondFactorContext::Login, PasskeyPurpose::SecondFactorLogin],
    [SecondFactorContext::PasswordReset, PasskeyPurpose::PasswordReset],
    [SecondFactorContext::IdentityConfirmation, PasskeyPurpose::IdentityConfirmation],
    [SecondFactorContext::PasswordChange, PasskeyPurpose::PasswordChange],
]);
