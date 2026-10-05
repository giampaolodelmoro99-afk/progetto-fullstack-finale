<?php

use App\Models\User;

it('offers the totp and the recovery codes to a user without passkeys', function () {
    expect(userWithTwoFactor()->secondFactorMethods())->toBe(['totp', 'recovery_code']);
});

it('offers the passkey too to a user who has one', function () {
    expect(User::factory()->withTwoFactor()->withPasskey()->create()->secondFactorMethods())
        ->toBe(['totp', 'recovery_code', 'passkey']);
});

it('offers no method without two-factor authentication, even with passkeys', function () {
    $user = User::factory()->withPasskey()->create();

    expect($user->secondFactorMethods())->toBe([])
        ->and($user->hasSecondFactor())->toBeFalse();
});
