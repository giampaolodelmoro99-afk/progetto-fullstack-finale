<?php

use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-01 10:30:00');
});

it('rejects a guest', function () {
    fromFrontend()->getJson('/api/v1/me/security')
        ->assertUnauthorized();
});

it('shows the overview without two-factor authentication', function () {
    actingAsCustomer(User::factory()->create([
        'password_changed_at' => '2026-09-12 21:31:00',
        'email_verified_at' => '2026-09-01 09:00:00',
    ]));

    fromFrontend()->getJson('/api/v1/me/security')
        ->assertOk()
        ->assertExactJson(['data' => [
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'recovery_codes_remaining' => 0,
            'recovery_codes_total' => 0,
            'passkeys_count' => 0,
            'email_verified' => true,
            'password_changed_at' => '2026-09-12T21:31:00+02:00',
        ]]);
});

it('shows the overview with two-factor authentication and the codes left', function () {
    $user = userWithTwoFactor([
        'two_factor_confirmed_at' => '2026-09-12 21:40:00',
        'password_changed_at' => '2026-09-12 21:31:00',
    ]);
    actingAsCustomer($user);

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'recovery_code', 'code' => 'AB12-CD34'])->assertOk();
    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'recovery_code', 'code' => 'EF56-GH78'])->assertOk();

    fromFrontend()->getJson('/api/v1/me/security')
        ->assertOk()
        ->assertExactJson(['data' => [
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => '2026-09-12T21:40:00+02:00',
            'recovery_codes_remaining' => 6,
            'recovery_codes_total' => 8,
            'passkeys_count' => 0,
            'email_verified' => true,
            'password_changed_at' => '2026-09-12T21:31:00+02:00',
        ]]);
});

it('counts the passkeys of the user only', function () {
    $user = actingAsCustomer(User::factory()->withPasskey('MacBook di Giulia')->withPasskey('iPhone di Giulia')->create());
    User::factory()->withPasskey()->create();

    fromFrontend()->getJson('/api/v1/me/security')
        ->assertOk()
        ->assertJsonPath('data.passkeys_count', 2)
        ->assertJsonPath('data.two_factor_enabled', false);

    expect($user->hasSecondFactor())->toBeFalse();
});

it('shows an unverified email', function () {
    actingAsCustomer();

    fromFrontend()->getJson('/api/v1/me/security')
        ->assertOk()
        ->assertJsonPath('data.email_verified', false);
});

it('ignores a secret waiting for confirmation', function () {
    actingAsCustomer(User::factory()->create(['two_factor_secret' => totpSecret()]));

    fromFrontend()->getJson('/api/v1/me/security')
        ->assertOk()
        ->assertJsonPath('data.two_factor_enabled', false)
        ->assertJsonPath('data.recovery_codes_total', 0);
});

it('never returns the two-factor secrets', function () {
    actingAsCustomer(userWithTwoFactor());

    $content = fromFrontend()->getJson('/api/v1/me/security')->assertOk()->getContent();

    expect($content)->not->toContain(totpSecret())
        ->not->toContain('two_factor_secret')
        ->not->toContain('two_factor_recovery_codes');
});
