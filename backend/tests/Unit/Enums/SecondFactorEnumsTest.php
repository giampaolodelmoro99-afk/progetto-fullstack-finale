<?php

use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;

it('labels the second factor methods in italian', function (SecondFactorMethod $method, string $value, string $label) {
    expect($method->value)->toBe($value)
        ->and($method->label())->toBe($label);
})->with([
    [SecondFactorMethod::Totp, 'totp', 'App di autenticazione'],
    [SecondFactorMethod::RecoveryCode, 'recovery_code', 'Codice di recupero'],
]);

it('offers only the methods of this phase', function () {
    expect(SecondFactorMethod::cases())->toHaveCount(2);
});

it('labels the second factor contexts in italian', function (SecondFactorContext $context, string $value, string $label) {
    expect($context->value)->toBe($value)
        ->and($context->label())->toBe($label);
})->with([
    [SecondFactorContext::Login, 'login', 'Accesso'],
    [SecondFactorContext::PasswordReset, 'password_reset', 'Recupero password'],
    [SecondFactorContext::IdentityConfirmation, 'identity_confirmation', 'Conferma dell\'identità'],
]);
