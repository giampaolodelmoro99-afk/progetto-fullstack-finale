<?php

use App\Enums\UserRole;
use App\Models\User;

it('has a second factor only with a confirmed totp', function (?string $confirmedAt, bool $expected) {
    $user = (new User)->forceFill([
        'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => $confirmedAt,
    ]);

    expect($user->hasSecondFactor())->toBe($expected);
})->with([
    'confirmed' => ['2026-09-12 21:40', true],
    'secret generated but not confirmed' => [null, false],
]);

it('counts the remaining recovery codes', function () {
    $user = (new User)->forceFill([
        'two_factor_recovery_codes' => ['hash-1', 'hash-2', 'hash-3', 'hash-4', 'hash-5', 'hash-6'],
        'two_factor_confirmed_at' => '2026-09-12 21:40',
    ]);

    expect($user->recovery_codes_remaining)->toBe(6);
});

it('has no recovery codes without two-factor authentication', function () {
    expect((new User)->recovery_codes_remaining)->toBe(0);
});

it('is an admin only with the admin role', function (UserRole $role, bool $isAdmin) {
    expect((new User)->forceFill(['role' => $role])->isAdmin())->toBe($isAdmin);
})->with([
    [UserRole::Admin, true],
    [UserRole::Customer, false],
]);

it('is a customer when no role is given', function () {
    expect((new User)->role)->toBe(UserRole::Customer);
});
