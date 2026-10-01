<?php

use App\Models\User;
use App\Services\Security\TotpVerifier;

beforeEach(function () {
    $this->travelTo('2026-10-01 10:30:00');
});

it('accepts the code of the current interval and records it', function () {
    $user = userWithTwoFactor();

    expect(app(TotpVerifier::class)->verify($user, ['code' => totpCode(totpSecret())]))->toBeTrue()
        ->and($user->fresh()->two_factor_last_used_step)->toBe(59694780);
});

it('accepts the codes of one interval before and after', function (int $offset, int $step) {
    $user = userWithTwoFactor();

    expect(app(TotpVerifier::class)->verify($user, ['code' => totpCode(totpSecret(), $offset)]))->toBeTrue()
        ->and($user->fresh()->two_factor_last_used_step)->toBe($step);
})->with([
    'previous interval' => [-1, 59694779],
    'next interval' => [1, 59694781],
]);

it('rejects the codes two intervals away', function (int $offset) {
    $user = userWithTwoFactor();

    expect(app(TotpVerifier::class)->verify($user, ['code' => totpCode(totpSecret(), $offset)]))->toBeFalse()
        ->and($user->fresh()->two_factor_last_used_step)->toBeNull();
})->with([
    'two intervals before' => [-2],
    'two intervals after' => [2],
]);

it('rejects a code already used', function () {
    $user = userWithTwoFactor();
    $code = totpCode(totpSecret());

    expect(app(TotpVerifier::class)->verify($user, ['code' => $code]))->toBeTrue()
        ->and(app(TotpVerifier::class)->verify($user->fresh(), ['code' => $code]))->toBeFalse();
});

it('rejects the interval before one already used', function () {
    $user = userWithTwoFactor();

    expect(app(TotpVerifier::class)->verify($user, ['code' => totpCode(totpSecret())]))->toBeTrue()
        ->and(app(TotpVerifier::class)->verify($user->fresh(), ['code' => totpCode(totpSecret(), -1)]))->toBeFalse();
});

it('accepts a code with spaces', function () {
    $user = userWithTwoFactor();
    $code = totpCode(totpSecret());

    expect(app(TotpVerifier::class)->verify($user, ['code' => substr($code, 0, 3).' '.substr($code, 3)]))->toBeTrue();
});

it('rejects wrong and malformed codes', function (mixed $code) {
    $user = userWithTwoFactor();

    expect(app(TotpVerifier::class)->verify($user, ['code' => $code]))->toBeFalse();
})->with([
    'wrong code' => ['000000'],
    'five digits' => ['12345'],
    'letters' => ['abcdef'],
    'empty' => [''],
    'missing' => [null],
]);

it('rejects any code without a secret', function () {
    $user = User::factory()->create();

    expect(app(TotpVerifier::class)->verify($user, ['code' => totpCode(totpSecret())]))->toBeFalse();
});
