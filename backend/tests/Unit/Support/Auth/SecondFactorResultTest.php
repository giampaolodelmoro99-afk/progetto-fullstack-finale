<?php

use App\Enums\SecondFactorMethod;
use App\Support\Auth\SecondFactorResult;

it('signals the recovery codes left after a recovery code', function (int $remaining, bool $low) {
    expect((new SecondFactorResult(SecondFactorMethod::RecoveryCode, $remaining))->meta())->toBe([
        'meta' => [
            'recovery_codes_remaining' => $remaining,
            'recovery_codes_low' => $low,
        ],
    ]);
})->with([
    '7 left' => [7, false],
    '3 left' => [3, false],
    '2 left' => [2, true],
    'none left' => [0, true],
]);

it('adds no meta after a code of the authenticator app', function () {
    expect((new SecondFactorResult(SecondFactorMethod::Totp, 8))->meta())->toBe([]);
});

it('names the passkey in the login event only after a passkey', function () {
    expect((new SecondFactorResult(SecondFactorMethod::Passkey, 8, 'MacBook di Davide'))->loginEventMetadata())
        ->toBe(['passkey_name' => 'MacBook di Davide'])
        ->and((new SecondFactorResult(SecondFactorMethod::Passkey, 8, 'MacBook di Davide'))->meta())->toBe([])
        ->and((new SecondFactorResult(SecondFactorMethod::Totp, 8))->loginEventMetadata())->toBe([]);
});
