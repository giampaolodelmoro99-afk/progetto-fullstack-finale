<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\RecoveryCodeUsedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

it('rejects a guest', function () {
    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])
        ->assertUnauthorized();
});

it('confirms the identity with the password alone without two-factor authentication', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])
        ->assertOk()
        ->assertExactJson(['data' => ['confirmed_until' => '2026-10-01T10:40:00+02:00']])
        ->assertSessionHas('auth.identity_confirmed_at', 1790843400);
});

it('rejects a wrong password', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'sbagliata'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'La password non è corretta.')
        ->assertSessionMissing('auth.identity_confirmed_at');
});

it('requires the password', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->postJson('/api/v1/me/confirm-identity', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('confirms the identity with password and code of the authenticator app', function () {
    actingAsCustomer(userWithTwoFactor());

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertOk()
        ->assertExactJson(['data' => ['confirmed_until' => '2026-10-01T10:40:00+02:00']])
        ->assertSessionHas('auth.identity_confirmed_at', 1790843400);
});

it('confirms the identity with a recovery code and signals the codes left', function () {
    $user = actingAsCustomer(userWithTwoFactor());

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'recovery_code', 'code' => 'AB12-CD34'])
        ->assertOk()
        ->assertExactJson([
            'data' => ['confirmed_until' => '2026-10-01T10:40:00+02:00'],
            'meta' => ['recovery_codes_remaining' => 7, 'recovery_codes_low' => false],
        ]);

    expect(SecurityEvent::sole()->type)->toBe(SecurityEventType::RecoveryCodeUsed);
    Notification::assertSentTo($user, RecoveryCodeUsedNotification::class);
});

it('requires the second factor with two-factor authentication', function () {
    actingAsCustomer(userWithTwoFactor());

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Il campo codice è obbligatorio.')
        ->assertJsonPath('errors.method.0', 'Il campo metodo è obbligatorio.')
        ->assertSessionMissing('auth.identity_confirmed_at');

    expect(SecurityEvent::count())->toBe(0);
});

it('rejects a wrong code and records the attempt', function (string $method, string $code) {
    $user = actingAsCustomer(userWithTwoFactor());

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => $method, 'code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Codice non valido.')
        ->assertJsonMissingPath('code')
        ->assertSessionMissing('auth.identity_confirmed_at');

    $event = SecurityEvent::sole();

    expect($event->user_id)->toBe($user->id)
        ->and($event->type)->toBe(SecurityEventType::TwoFactorChallengeFailed)
        ->and($event->metadata)->toEqual(['context' => 'identity_confirmation', 'method' => $method])
        ->and(json_encode($event->getAttributes()))->not->toContain($code);
})->with([
    'totp' => ['totp', '000000'],
    'recovery code' => ['recovery_code', 'ZZZZ-ZZZZ'],
]);

it('checks the password before the second factor and does not consume the code', function () {
    $user = actingAsCustomer(userWithTwoFactor());

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'sbagliata', 'method' => 'recovery_code', 'code' => 'AB12-CD34'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect($user->fresh()->two_factor_recovery_codes)->toHaveCount(8)
        ->and(SecurityEvent::count())->toBe(0);
});

it('rejects an unknown method', function () {
    actingAsCustomer(userWithTwoFactor());

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'sms', 'code' => '123456'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);
});

it('limits the attempts to 5 per minute for each user', function () {
    actingAsVerifiedCustomer();

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'sbagliata'])
            ->assertUnprocessable();
    }

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('message', 'Troppi tentativi. Riprova tra 60 secondi.');

    forgetGuards();
    actingAsCustomer(User::factory()->create());

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])
        ->assertOk();
});

it('shares the count of wrong codes between login, password reset and identity confirmation', function () {
    Sleep::fake();
    $user = userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    $token = Password::broker()->createToken($user);

    foreach (range(1, 3) as $attempt) {
        fromFrontend()->postJson('/api/v1/reset-password', [
            'email' => 'davide.conti@esempio.it',
            'token' => $token,
            'password' => 'Montagna2026',
            'password_confirmation' => 'Montagna2026',
            'method' => 'totp',
            'code' => '000000',
        ])->assertUnprocessable();
    }

    fromFrontend()->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'password'])->assertOk();

    foreach (range(1, 2) as $attempt) {
        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'recovery_code', 'code' => 'ZZZZ-ZZZZ'])
            ->assertUnprocessable();
    }

    actingAsCustomer($user);

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'recovery_code', 'code' => 'AB12-CD34'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertSessionMissing('auth.identity_confirmed_at');

    expect($user->fresh()->two_factor_recovery_codes)->toHaveCount(8)
        ->and(SecurityEvent::where('type', SecurityEventType::TwoFactorChallengeFailed)->count())->toBe(5);
});
