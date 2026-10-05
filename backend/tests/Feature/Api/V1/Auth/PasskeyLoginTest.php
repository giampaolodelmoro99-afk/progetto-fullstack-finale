<?php

use App\Contracts\Security\PasskeyCeremony;
use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Security\WebAuthnPasskeyCeremony;

beforeEach(function () {
    fakePasskeys();
    $this->travelTo('2026-10-05 10:00:00');
});

/**
 * Asks for the options of the passwordless login: the next requests carry
 * the cookie of the session that keeps them, as the browser does.
 */
function startPasskeyLogin(): void
{
    fromFrontend()->withCredentials()->postJson('/api/v1/passkeys/login/options')->assertOk();

    test()->withCookie(config('session.cookie'), session()->getId());
}

function invalidPasskeyResponse(): array
{
    return [
        'message' => 'Passkey non riconosciuta: riprova o accedi con la password.',
        'code' => 'invalid_passkey',
    ];
}

it('answers the options of the passwordless login', function () {
    fromFrontend()->postJson('/api/v1/passkeys/login/options')
        ->assertOk()
        ->assertJsonStructure(['data' => ['options' => ['challenge']]]);
});

it('gives the real library options without a list of credentials', function () {
    app()->bind(PasskeyCeremony::class, WebAuthnPasskeyCeremony::class);

    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');

    expect($options['rpId'])->toBe('localhost')
        ->and($options['userVerification'])->toBe('required')
        ->and($options['allowCredentials'])->toBe([])
        ->and($options['timeout'])->toBe(60000);
});

it('logs in with the passkey alone', function () {
    $user = User::factory()->create(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it']);
    $passkey = passkeyFor($user);
    startPasskeyLogin();
    $optionsSessionId = session()->getId();

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer()])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'giulia.ferri@esempio.it')
        ->assertJsonPath('data.pending_email_change', null);

    $this->assertAuthenticatedAs($user, 'web');

    $event = SecurityEvent::sole();

    expect(session()->getId())->not->toBe($optionsSessionId)
        ->and($event->type)->toBe(SecurityEventType::Login)
        ->and($event->metadata)->toBe(['passkey_name' => 'MacBook di Giulia'])
        ->and($passkey->fresh()->last_used_at->toIso8601String())->toBe('2026-10-05T10:00:00+02:00');
});

it('keeps the user logged in on the next requests', function () {
    passkeyFor(User::factory()->create(['email' => 'giulia.ferri@esempio.it']));
    startPasskeyLogin();

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer()])->assertOk();
    $this->withCookie(config('session.cookie'), session()->getId());
    forgetGuards();

    fromFrontend()->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'giulia.ferri@esempio.it');
});

it('logs in with the passkey alone also with two-factor authentication', function () {
    $user = userWithTwoFactor();
    passkeyFor($user);
    startPasskeyLogin();

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer()])
        ->assertOk()
        ->assertJsonMissingPath('two_factor_required');

    $this->assertAuthenticatedAs($user, 'web');
});

it('drops a pending password challenge and an old identity confirmation', function () {
    $user = User::factory()->create();
    passkeyFor($user);
    startPasskeyLogin();
    session()->put(['login.pending_user_id' => 99, 'login.pending_at' => now()->getTimestamp(), 'auth.identity_confirmed_at' => now()->getTimestamp()]);

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer()])
        ->assertOk()
        ->assertSessionMissing('login.pending_user_id')
        ->assertSessionMissing('login.pending_at')
        ->assertSessionMissing('auth.identity_confirmed_at');
});

it('rejects every failure with the same answer and no event', function (string $case) {
    $user = User::factory()->create();
    passkeyFor($user);

    if ($case === 'anonymized account') {
        $user->forceFill(['anonymized_at' => now()])->save();
    }

    if ($case !== 'without options') {
        startPasskeyLogin();
    }

    if ($case === 'expired options') {
        $this->travel(301)->seconds();
    }

    $credential = match ($case) {
        'unknown credential' => passkeyAnswer('c2Nvbm9zY2l1dGE'),
        'wrong signature' => passkeyAnswer(valid: false),
        default => passkeyAnswer(),
    };

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $credential])
        ->assertUnprocessable()
        ->assertExactJson(invalidPasskeyResponse());

    $this->assertGuest('web');
    expect(SecurityEvent::count())->toBe(0);
})->with([
    'without options',
    'expired options',
    'unknown credential',
    'wrong signature',
    'anonymized account',
]);

it('accepts the options for 300 seconds', function () {
    passkeyFor(User::factory()->create());
    startPasskeyLogin();
    $this->travel(300)->seconds();

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer()])->assertOk();
});

it('uses the options only once, also after a failure', function () {
    passkeyFor(User::factory()->create());
    startPasskeyLogin();

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer(valid: false)])
        ->assertUnprocessable();

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer()])
        ->assertUnprocessable()
        ->assertExactJson(invalidPasskeyResponse());

    $this->assertGuest('web');
});

it('does not accept the options of another purpose', function () {
    $user = userWithTwoFactor();
    passkeyFor($user);
    actingAsCustomer($user);
    fromFrontend()->postJson('/api/v1/me/confirm-identity/passkey-options')->assertOk();
    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer()])
        ->assertUnprocessable()
        ->assertExactJson(invalidPasskeyResponse());
});

it('requires the answer of the browser', function (array $data) {
    fromFrontend()->postJson('/api/v1/passkeys/login', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credential']);
})->with([
    'missing' => [[]],
    'not an object' => [['credential' => 'abc']],
]);

it('limits options and logins to 10 per minute for each ip address', function () {
    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk();
        fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => passkeyAnswer()])->assertUnprocessable();
    }

    fromFrontend()->postJson('/api/v1/passkeys/login/options')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('message', 'Troppi tentativi. Riprova tra 60 secondi.');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);

    fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk();
});
