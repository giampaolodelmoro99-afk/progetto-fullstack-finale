<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Sleep;

function loginCredentials(array $overrides = []): array
{
    return array_merge(['email' => 'giulia.ferri@esempio.it', 'password' => 'password'], $overrides);
}

beforeEach(function () {
    Sleep::fake();
});

it('logs in a customer without two-factor authentication', function () {
    $user = User::factory()->create(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', loginCredentials())
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Giulia Ferri')
        ->assertJsonPath('data.email', 'giulia.ferri@esempio.it')
        ->assertJsonMissingPath('two_factor_required');

    $this->assertAuthenticatedAs($user, 'web');
});

it('accepts the email in any letter case', function () {
    User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', loginCredentials(['email' => ' Giulia.Ferri@Esempio.it ']))
        ->assertOk();
});

it('regenerates the session id', function () {
    User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $sessionId = str_repeat('a', 40);

    fromFrontend()->withCookie(config('session.cookie'), $sessionId)
        ->postJson('/api/v1/login', loginCredentials())
        ->assertOk();

    expect(session()->getId())->not->toBe($sessionId);
});

it('records the login event with ip address and user agent', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36')
        ->postJson('/api/v1/login', loginCredentials())
        ->assertOk();

    $event = SecurityEvent::sole();

    expect($event->user_id)->toBe($user->id)
        ->and($event->type)->toBe(SecurityEventType::Login)
        ->and($event->ip_address)->toBe('127.0.0.1')
        ->and($event->user_agent)->toBe('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36');
});

it('answers the same way for a wrong password and an unknown email', function (array $credentials) {
    User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', $credentials)
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Credenziali non valide',
            'code' => 'invalid_credentials',
        ]);

    $this->assertGuest('web');
})->with([
    'wrong password' => [['email' => 'giulia.ferri@esempio.it', 'password' => 'sbagliata']],
    'unknown email' => [['email' => 'nessuno@esempio.it', 'password' => 'password']],
]);

it('makes every failed attempt last the same time', function (string $email) {
    User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', ['email' => $email, 'password' => 'sbagliata'])
        ->assertUnprocessable();

    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn ($duration) => $duration->totalMicroseconds > 0 && $duration->totalMicroseconds <= 200000);
})->with([
    'unknown email' => 'nessuno@esempio.it',
    'wrong password' => 'giulia.ferri@esempio.it',
]);

it('answers at once when the credentials are right', function (bool $twoFactor) {
    $factory = User::factory();
    ($twoFactor ? $factory->withTwoFactor() : $factory)->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', loginCredentials())->assertOk();

    Sleep::assertNeverSlept();
})->with([
    'without two-factor authentication' => false,
    'with two-factor authentication' => true,
]);

it('records a failed login for an existing user', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', loginCredentials(['password' => 'sbagliata']))
        ->assertUnprocessable();

    $event = SecurityEvent::sole();

    expect($event->user_id)->toBe($user->id)
        ->and($event->type)->toBe(SecurityEventType::LoginFailed);
});

it('records nothing for an unknown email', function () {
    fromFrontend()->postJson('/api/v1/login', loginCredentials(['email' => 'nessuno@esempio.it']))
        ->assertUnprocessable();

    expect(SecurityEvent::count())->toBe(0);
});

it('refuses an anonymized account without recording a failed login', function () {
    $user = User::factory()->create(['email' => 'deleted-7@anonymized.invalid']);
    $user->forceFill(['anonymized_at' => '2026-09-01 10:00:00'])->save();

    fromFrontend()->postJson('/api/v1/login', ['email' => 'deleted-7@anonymized.invalid', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'Credenziali non valide', 'code' => 'invalid_credentials']);

    $this->assertGuest('web');
    expect(SecurityEvent::count())->toBe(0);
});

it('asks for the second factor without authenticating', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = User::factory()->withTwoFactor()->create(['email' => 'davide.conti@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'password'])
        ->assertOk()
        ->assertExactJson([
            'two_factor_required' => true,
            'methods' => ['totp', 'recovery_code'],
        ])
        ->assertSessionHas('login.pending_user_id', $user->id)
        ->assertSessionHas('login.pending_at', 1790843400);

    $this->assertGuest('web');
    expect(SecurityEvent::count())->toBe(0);
});

it('offers the passkey in the challenge to a user who has one', function () {
    User::factory()->withTwoFactor()->withPasskey()->create(['email' => 'davide.conti@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'password'])
        ->assertOk()
        ->assertExactJson([
            'two_factor_required' => true,
            'methods' => ['totp', 'recovery_code', 'passkey'],
        ]);
});

it('logs in with the password alone a user with passkeys only', function () {
    $user = User::factory()->withPasskey()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', ['email' => 'giulia.ferri@esempio.it', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissingPath('two_factor_required');

    $this->assertAuthenticatedAs($user, 'web');
    expect($user->hasSecondFactor())->toBeFalse();
});

it('logs out another account and regenerates the session before the challenge', function () {
    actingAsVerifiedCustomer();
    User::factory()->withTwoFactor()->create(['email' => 'davide.conti@esempio.it']);
    $sessionId = str_repeat('b', 40);

    fromFrontend()->withCookie(config('session.cookie'), $sessionId)
        ->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('two_factor_required', true);

    $this->assertGuest('web');
    expect(session()->getId())->not->toBe($sessionId);
});

it('rejects a wrong password before asking for the second factor', function () {
    User::factory()->withTwoFactor()->create(['email' => 'davide.conti@esempio.it']);

    fromFrontend()->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'sbagliata'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_credentials')
        ->assertSessionMissing('login.pending_user_id');
});

it('validates the fields', function (array $credentials, string $field) {
    fromFrontend()->postJson('/api/v1/login', $credentials)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing email' => [['password' => 'password'], 'email'],
    'malformed email' => [['email' => 'giulia', 'password' => 'password'], 'email'],
    'missing password' => [['email' => 'giulia.ferri@esempio.it'], 'password'],
]);

it('limits the attempts to 5 per minute for each email and ip', function () {
    $this->freezeTime();

    User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/login', loginCredentials(['password' => 'sbagliata']))
            ->assertUnprocessable();
    }

    fromFrontend()->postJson('/api/v1/login', loginCredentials())
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('message', 'Troppi tentativi. Riprova tra 60 secondi.');

    fromFrontend()->postJson('/api/v1/login', loginCredentials(['email' => 'altro@esempio.it']))
        ->assertUnprocessable();
});
