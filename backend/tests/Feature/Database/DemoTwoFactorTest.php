<?php

use App\Models\User;
use App\Services\Security\RecoveryCodeVerifier;
use App\Services\Security\TotpVerifier;
use App\Services\Security\TwoFactorQrCode;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;

/**
 * The secret, the URI and the recovery codes of Davide Conti are written by
 * hand as published in the README, not read from the seeder.
 */
beforeEach(function () {
    $this->travelTo('2026-10-01 10:30:00');
    $this->seed(UserSeeder::class);
    $this->davide = User::query()->where('email', 'davide.conti@esempio.it')->sole();
});

it('accepts a code generated from the secret of the README', function () {
    expect($this->davide->hasSecondFactor())->toBeTrue()
        ->and(app(TotpVerifier::class)->verify($this->davide, ['code' => totpCode('CASADELSILICIODEMOTOTPSEGRETO234')]))->toBeTrue();
});

it('accepts each recovery code of the README once', function (string $code) {
    expect(app(RecoveryCodeVerifier::class)->verify($this->davide, ['code' => $code]))->toBeTrue()
        ->and(app(RecoveryCodeVerifier::class)->verify($this->davide->fresh(), ['code' => $code]))->toBeFalse()
        ->and($this->davide->fresh()->two_factor_recovery_codes)->toHaveCount(7);
})->with(['K7PM-2XQA', 'R4TD-9WNE', 'B8HV-3LCJ', 'M2QF-7YRS', 'T6ZN-4GKP', 'W9CX-5DHB', 'E3JL-8VMT', 'P5RY-6NAF']);

it('builds the otpauth uri of the README', function () {
    expect(app(TwoFactorQrCode::class)->url('davide.conti@esempio.it', 'CASADELSILICIODEMOTOTPSEGRETO234'))
        ->toBe('otpauth://totp/Casa%20del%20Silicio:davide.conti%40esempio.it?secret=CASADELSILICIODEMOTOTPSEGRETO234&issuer=Casa%20del%20Silicio');
});

it('logs in through the api with the demo password and a recovery code of the README', function () {
    Sleep::fake();
    Notification::fake();

    fromFrontend()->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'Silicio2026'])
        ->assertOk()
        ->assertJsonPath('two_factor_required', true);

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'recovery_code', 'code' => 'P5RY-6NAF'])
        ->assertOk()
        ->assertJsonPath('data.email', 'davide.conti@esempio.it')
        ->assertJsonPath('meta.recovery_codes_remaining', 7);

    $this->assertAuthenticatedAs($this->davide, 'web');
});
