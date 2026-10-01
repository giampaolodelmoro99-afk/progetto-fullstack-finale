<?php

use App\Models\User;
use App\Services\Security\RecoveryCodeVerifier;
use Illuminate\Support\Facades\Hash;

it('accepts a recovery code once and removes it', function () {
    $user = userWithTwoFactor();

    expect(app(RecoveryCodeVerifier::class)->verify($user, ['code' => 'EF56-GH78']))->toBeTrue();

    $user->refresh();

    expect($user->two_factor_recovery_codes)->toHaveCount(7)
        ->and(collect($user->two_factor_recovery_codes)->contains(fn (string $hash): bool => Hash::check('EF56-GH78', $hash)))->toBeFalse()
        ->and(app(RecoveryCodeVerifier::class)->verify($user, ['code' => 'EF56-GH78']))->toBeFalse()
        ->and($user->fresh()->two_factor_recovery_codes)->toHaveCount(7);
});

it('accepts a code in lower case or with spaces', function (string $code) {
    $user = userWithTwoFactor();

    expect(app(RecoveryCodeVerifier::class)->verify($user, ['code' => $code]))->toBeTrue();
})->with([
    'lower case' => ['ab12-cd34'],
    'spaces around' => ['  AB12-CD34 '],
]);

it('rejects wrong, empty and missing codes', function (mixed $code) {
    $user = userWithTwoFactor();

    expect(app(RecoveryCodeVerifier::class)->verify($user, ['code' => $code]))->toBeFalse()
        ->and($user->fresh()->two_factor_recovery_codes)->toHaveCount(8);
})->with([
    'wrong code' => ['ZZZZ-ZZZZ'],
    'without the dash' => ['AB12CD34'],
    'empty' => [''],
    'missing' => [null],
]);

it('rejects any code without recovery codes', function () {
    $user = User::factory()->create();

    expect(app(RecoveryCodeVerifier::class)->verify($user, ['code' => 'AB12-CD34']))->toBeFalse();
});
