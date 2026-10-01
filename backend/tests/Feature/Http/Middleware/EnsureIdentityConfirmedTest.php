<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->travelTo('2026-10-01 10:30:00');

    Gate::define('never', fn (User $user): bool => false);

    Route::middleware(['api', 'auth:sanctum', 'identity.confirmed'])
        ->post('/api/v1/testing/sensitive', fn () => response()->json(['done' => true]));

    Route::middleware(['api', 'auth:sanctum', 'identity.confirmed', 'can:never'])
        ->post('/api/v1/testing/forbidden', fn () => response()->json(['done' => true]));
});

it('asks for the password without two-factor authentication', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->postJson('/api/v1/testing/sensitive')
        ->assertStatus(423)
        ->assertExactJson([
            'message' => 'Conferma la tua identità per continuare.',
            'requires' => ['password'],
        ]);
});

it('asks for password and second factor with two-factor authentication', function () {
    actingAsCustomer(userWithTwoFactor());

    fromFrontend()->postJson('/api/v1/testing/sensitive')
        ->assertStatus(423)
        ->assertExactJson([
            'message' => 'Conferma la tua identità per continuare.',
            'requires' => ['password', 'second_factor'],
        ]);
});

it('lets the request through for 600 seconds after the confirmation', function () {
    actingAsVerifiedCustomer();
    confirmIdentity();
    $this->travel(600)->seconds();

    fromFrontend()->postJson('/api/v1/testing/sensitive')
        ->assertOk()
        ->assertExactJson(['done' => true]);
});

it('asks again 601 seconds after the confirmation', function () {
    actingAsVerifiedCustomer();
    confirmIdentity();
    $this->travel(601)->seconds();

    fromFrontend()->postJson('/api/v1/testing/sensitive')
        ->assertStatus(423);
});

it('works with a confirmation made through the endpoint', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])->assertOk();
    $this->withCookie(config('session.cookie'), session()->getId());

    fromFrontend()->postJson('/api/v1/testing/sensitive')->assertOk();
});

it('forgets the confirmation at the logout', function () {
    User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    loginThroughApi('giulia.ferri@esempio.it');

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])->assertOk();
    fromFrontend()->postJson('/api/v1/testing/sensitive')->assertOk();
    forgetGuards();

    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();
    loginThroughApi('giulia.ferri@esempio.it');

    fromFrontend()->postJson('/api/v1/testing/sensitive')->assertStatus(423);
});

it('drops the confirmation of the previous user at the login', function (bool $twoFactor) {
    actingAsVerifiedCustomer();
    confirmIdentity();
    forgetGuards();
    $twoFactor
        ? userWithTwoFactor(['email' => 'davide.conti@esempio.it'])
        : User::factory()->create(['email' => 'davide.conti@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'password'])
        ->assertOk()
        ->assertSessionMissing('auth.identity_confirmed_at');

    if ($twoFactor) {
        confirmIdentity();

        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
            ->assertOk()
            ->assertSessionMissing('auth.identity_confirmed_at');
    }

    fromFrontend()->postJson('/api/v1/testing/sensitive')->assertStatus(423);
})->with([
    'without two-factor authentication' => false,
    'with two-factor authentication' => true,
]);

it('checks the permission before the identity confirmation', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->postJson('/api/v1/testing/forbidden')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Non hai i permessi per questa operazione.']);
});

it('rejects a guest before asking for the confirmation', function () {
    fromFrontend()->postJson('/api/v1/testing/sensitive')
        ->assertUnauthorized();
});
