<?php

use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;

it('labels the second factor methods in italian', function (SecondFactorMethod $method, string $value, string $label) {
    expect($method->value)->toBe($value)
        ->and($method->label())->toBe($label);
})->with([
    [SecondFactorMethod::Totp, 'totp', 'App di autenticazione'],
    [SecondFactorMethod::RecoveryCode, 'recovery_code', 'Codice di recupero'],
    [SecondFactorMethod::Passkey, 'passkey', 'Passkey'],
]);

it('offers the app, the recovery codes and the passkey', function () {
    expect(SecondFactorMethod::cases())->toHaveCount(3);
});

it('labels the second factor contexts in italian', function (SecondFactorContext $context, string $value, string $label) {
    expect($context->value)->toBe($value)
        ->and($context->label())->toBe($label);
})->with([
    [SecondFactorContext::Login, 'login', 'Accesso'],
    [SecondFactorContext::PasswordReset, 'password_reset', 'Recupero password'],
    [SecondFactorContext::IdentityConfirmation, 'identity_confirmation', 'Conferma dell\'identità'],
    [SecondFactorContext::PasswordChange, 'password_change', 'Cambio password'],
]);

it('defines only the contexts of this phase', function () {
    expect(SecondFactorContext::cases())->toHaveCount(4);
});
