<?php

use App\Enums\SecurityEventType;
use App\Exceptions\Auth\PasskeyCeremonyFailedException;
use App\Models\Passkey;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Tests\Support\SoftwareAuthenticator;

/*
| The real WebAuthn library (laravel/passkeys on web-auth/webauthn-lib) with
| an authenticator in software: what the fake ceremony of the other tests
| cannot prove. Origin http://localhost:5173, relying party localhost, user
| verification required, challenge, signature and counter.
*/

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-05 10:00:00');
});

/**
 * Registers a passkey of the authenticator for the acting user through the
 * API and returns the response.
 */
function registerThroughApi(SoftwareAuthenticator $authenticator, string $origin = 'http://localhost:5173', bool $userVerified = true): TestResponse
{
    confirmIdentity();
    $options = fromFrontend()->postJson('/api/v1/me/passkeys/options')->assertOk()->json('data.options');

    return fromFrontend()->postJson('/api/v1/me/passkeys', [
        'name' => 'iPhone di Giulia',
        'credential' => $authenticator->register($options, $origin, $userVerified),
    ]);
}

/**
 * Registers a passkey for a new user, then logs out: the next requests are
 * those of a guest.
 */
function userWithRealPasskey(SoftwareAuthenticator $authenticator): User
{
    $user = actingAsVerifiedCustomer();
    registerThroughApi($authenticator)->assertCreated();

    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();

    return $user;
}

it('registers a passkey answered from the frontend origin with user verification', function () {
    $user = actingAsVerifiedCustomer();
    $authenticator = new SoftwareAuthenticator;

    registerThroughApi($authenticator)
        ->assertCreated()
        ->assertJsonPath('data.name', 'iPhone di Giulia');

    $passkey = Passkey::sole();

    expect($passkey->user_id)->toBe($user->id)
        ->and($passkey->credential_id)->toBe($authenticator->credentialIdBase64Url())
        ->and($passkey->credential['counter'])->toBe(0);
});

it('rejects a registration answered from another origin', function (string $origin) {
    actingAsVerifiedCustomer();

    registerThroughApi(new SoftwareAuthenticator, $origin)
        ->assertUnprocessable()
        ->assertJsonPath('errors.credential.0', 'Non è stato possibile registrare la passkey: riprova.');

    expect(Passkey::count())->toBe(0);
})->with([
    'backend address' => ['http://localhost:8000'],
    'https' => ['https://localhost:5173'],
    'other site' => ['http://evil.example:5173'],
]);

it('rejects a registration without user verification', function () {
    actingAsVerifiedCustomer();

    registerThroughApi(new SoftwareAuthenticator, userVerified: false)->assertUnprocessable();

    expect(Passkey::count())->toBe(0);
});

it('rejects the same credential registered twice', function () {
    actingAsVerifiedCustomer();
    $authenticator = new SoftwareAuthenticator;
    registerThroughApi($authenticator)->assertCreated();

    registerThroughApi($authenticator)->assertUnprocessable();

    expect(Passkey::count())->toBe(1);
});

it('logs in without password and saves the counter and the last use', function () {
    $authenticator = new SoftwareAuthenticator;
    $user = userWithRealPasskey($authenticator);
    $this->travel(5)->minutes();

    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $authenticator->authenticate($options)])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user, 'web');

    $passkey = Passkey::sole();

    expect($passkey->credential['counter'])->toBe(1)
        ->and($passkey->last_used_at->toIso8601String())->toBe('2026-10-05T10:05:00+02:00')
        ->and(SecurityEvent::query()->latest('id')->first()->metadata)->toBe(['passkey_name' => 'iPhone di Giulia']);
});

it('rejects a login that does not pass the checks of the library', function (array $tamper) {
    $authenticator = new SoftwareAuthenticator;
    userWithRealPasskey($authenticator);

    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $authenticator->authenticate($options, ...$tamper)])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Passkey non riconosciuta: riprova o accedi con la password.',
            'code' => 'invalid_passkey',
        ]);

    $this->assertGuest('web');
    expect(Passkey::sole()->last_used_at)->toBeNull();
})->with([
    'backend origin' => [['origin' => 'http://localhost:8000']],
    'without user verification' => [['userVerified' => false]],
    'another challenge' => [['challenge' => 'b3RoZXItY2hhbGxlbmdlLW9mLTMyLWJ5dGVzLWxvbmc']],
    'signature of another key' => [['signWithNewKey' => true]],
]);

it('rejects an answer replayed after a successful login', function () {
    $authenticator = new SoftwareAuthenticator;
    userWithRealPasskey($authenticator);

    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');
    $answer = $authenticator->authenticate($options);
    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $answer])->assertOk();

    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();
    fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk();

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $answer])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_passkey');
});

it('accepts the real passkey as second factor of the identity confirmation', function () {
    $user = actingAsCustomer(userWithTwoFactor());
    $authenticator = new SoftwareAuthenticator;
    registerThroughApi($authenticator)->assertCreated();
    session()->forget('auth.identity_confirmed_at');

    $options = fromFrontend()->postJson('/api/v1/me/confirm-identity/passkey-options')->assertOk()->json('data.options');

    expect($options['allowCredentials'])->toBe([[
        'type' => 'public-key',
        'id' => $authenticator->credentialIdBase64Url(),
    ]])->and($options['userVerification'])->toBe('required');

    fromFrontend()->postJson('/api/v1/me/confirm-identity', [
        'password' => 'password',
        'method' => 'passkey',
        'credential' => $authenticator->authenticate($options),
    ])->assertOk();

    expect($user->passkeys()->sole()->credential['counter'])->toBe(1);
});

it('rejects as second factor the real passkey of another user', function () {
    $authenticator = new SoftwareAuthenticator;
    userWithRealPasskey($authenticator);

    $other = actingAsCustomer(userWithTwoFactor());
    passkeyFor($other);
    $options = fromFrontend()->postJson('/api/v1/me/confirm-identity/passkey-options')->assertOk()->json('data.options');

    fromFrontend()->postJson('/api/v1/me/confirm-identity', [
        'password' => 'password',
        'method' => 'passkey',
        'credential' => $authenticator->authenticate($options),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.credential.0', 'Passkey non valida: riprova.');
});

it('rejects a signature counter that goes back, as a cloned authenticator sends it', function () {
    $authenticator = new SoftwareAuthenticator;
    userWithRealPasskey($authenticator);
    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');
    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $authenticator->authenticate($options)])->assertOk();
    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();
    $this->travel(10)->minutes();

    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $authenticator->authenticate($options, counter: 1)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_passkey');

    $this->assertGuest('web');

    $passkey = Passkey::sole();

    expect($passkey->credential['counter'])->toBe(1)
        ->and($passkey->last_used_at->toIso8601String())->toBe('2026-10-05T10:00:00+02:00');
});

it('accepts a synced passkey whose counter stays at 0', function () {
    $authenticator = new SoftwareAuthenticator;
    $user = userWithRealPasskey($authenticator);

    foreach (['2026-10-05T10:05:00+02:00', '2026-10-05T10:10:00+02:00'] as $expectedLastUse) {
        $this->travel(5)->minutes();
        $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');

        fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $authenticator->authenticate($options, counter: 0)])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $passkey = Passkey::sole();

        expect($passkey->credential['counter'])->toBe(0)
            ->and($passkey->last_used_at->toIso8601String())->toBe($expectedLastUse);

        fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
        forgetGuards();
    }
});

it('rejects a well formed login answer with malformed content as an invalid passkey', function (string $field, string $value) {
    $authenticator = new SoftwareAuthenticator;
    userWithRealPasskey($authenticator);
    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');
    $answer = $authenticator->authenticate($options);
    data_set($answer, $field, $value);

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $answer])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Passkey non riconosciuta: riprova o accedi con la password.',
            'code' => 'invalid_passkey',
        ]);

    $this->assertGuest('web');
})->with([
    'client data not in base64' => ['response.clientDataJSON', 'a b!'],
    'client data that is not json' => ['response.clientDataJSON', 'bm9uLWpzb24'],
    'authenticator data not in base64' => ['response.authenticatorData', '%%%'],
    'authenticator data too short' => ['response.authenticatorData', 'AAAA'],
    'signature not in base64' => ['response.signature', '***'],
    'raw id not in base64' => ['rawId', '!!!'],
]);

it('rejects a well formed registration answer with malformed content', function (string $field, string $value) {
    actingAsVerifiedCustomer();
    confirmIdentity();
    $options = fromFrontend()->postJson('/api/v1/me/passkeys/options')->assertOk()->json('data.options');
    $answer = (new SoftwareAuthenticator)->register($options);
    data_set($answer, $field, $value);

    fromFrontend()->postJson('/api/v1/me/passkeys', ['name' => 'iPhone di Giulia', 'credential' => $answer])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Non è stato possibile registrare la passkey: riprova.',
            'errors' => ['credential' => ['Non è stato possibile registrare la passkey: riprova.']],
        ]);

    expect(Passkey::count())->toBe(0);
})->with([
    'client data not in base64' => ['response.clientDataJSON', 'a b!'],
    'attestation not in base64' => ['response.attestationObject', '%%%'],
    'attestation that is not cbor' => ['response.attestationObject', 'bm9uLWNib3I'],
]);

it('rejects a malformed shape on the field before the library', function (string $field, mixed $value, string $error) {
    $authenticator = new SoftwareAuthenticator;
    userWithRealPasskey($authenticator);
    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');
    $answer = $authenticator->authenticate($options);
    data_set($answer, $field, $value);

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $answer])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$error])
        ->assertJsonMissingPath('code');
})->with([
    'id that is not a string' => ['id', 42, 'credential.id'],
    'type of another credential' => ['type', 'password', 'credential.type'],
    'response that is not an object' => ['response', 'abc', 'credential.response'],
    'client data that is not a string' => ['response.clientDataJSON', ['a' => 'b'], 'credential.response.clientDataJSON'],
    'signature that is not a string' => ['response.signature', 123, 'credential.response.signature'],
    'transports that are not strings' => ['response.transports', [1], 'credential.response.transports.0'],
]);

it('rejects a malformed shape of the registration on the field', function () {
    actingAsVerifiedCustomer();
    confirmIdentity();
    $options = fromFrontend()->postJson('/api/v1/me/passkeys/options')->assertOk()->json('data.options');
    $answer = (new SoftwareAuthenticator)->register($options);
    data_set($answer, 'response.attestationObject', ['fmt' => 'none']);

    fromFrontend()->postJson('/api/v1/me/passkeys', ['name' => 'iPhone di Giulia', 'credential' => $answer])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credential.response.attestationObject']);
});

it('counts malformed passkey content as a failed second factor attempt', function () {
    $user = actingAsCustomer(userWithTwoFactor(['email' => 'davide.conti@esempio.it']));
    $authenticator = new SoftwareAuthenticator;
    registerThroughApi($authenticator)->assertCreated();
    $token = Password::broker()->createToken($user);

    $malformed = function (array $options) use ($authenticator): array {
        $answer = $authenticator->authenticate($options);
        data_set($answer, 'response.clientDataJSON', 'a b!');

        return $answer;
    };

    foreach (range(1, 2) as $attempt) {
        $options = fromFrontend()->postJson('/api/v1/me/password/passkey-options')->assertOk()->json('data.options');

        fromFrontend()->putJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'Montagna2026',
            'password_confirmation' => 'Montagna2026',
            'method' => 'passkey',
            'credential' => $malformed($options),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.credential.0', 'Passkey non valida: riprova.');
    }

    foreach (range(1, 3) as $attempt) {
        $options = fromFrontend()->postJson('/api/v1/reset-password/passkey-options', ['email' => 'davide.conti@esempio.it', 'token' => $token])
            ->assertOk()
            ->json('data.options');

        fromFrontend()->postJson('/api/v1/reset-password', [
            'email' => 'davide.conti@esempio.it',
            'token' => $token,
            'password' => 'Montagna2026',
            'password_confirmation' => 'Montagna2026',
            'method' => 'passkey',
            'credential' => $malformed($options),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.credential.0', 'Passkey non valida: riprova.');
    }

    $failures = SecurityEvent::query()->where('type', SecurityEventType::TwoFactorChallengeFailed)->orderBy('id')->get();

    expect($failures)->toHaveCount(5)
        ->and($failures->first()->metadata)->toEqual(['context' => 'password_change', 'method' => 'passkey'])
        ->and($failures->last()->metadata)->toEqual(['context' => 'password_reset', 'method' => 'passkey']);

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
});

it('reports an unexpected error of the library without the content of the credential', function () {
    $authenticator = new SoftwareAuthenticator;
    userWithRealPasskey($authenticator);
    $options = fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk()->json('data.options');
    $answer = $authenticator->authenticate($options);

    $verifyPasskey = Mockery::mock(VerifyPasskey::class);
    $verifyPasskey->shouldReceive('__invoke')->andThrow(new TypeError('VerifyPasskey::__invoke(): Argument #2 ($options) must be of type PublicKeyCredentialRequestOptions, array given'));
    app()->instance(VerifyPasskey::class, $verifyPasskey);

    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $message) use (&$logged) {
        $logged[] = $message;
    });

    fromFrontend()->postJson('/api/v1/passkeys/login', ['credential' => $answer])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_passkey');

    $reported = array_values(array_filter($logged, fn (MessageLogged $message) => ($message->context['exception'] ?? null) instanceof PasskeyCeremonyFailedException));
    $written = implode(' ', array_map(fn (MessageLogged $message) => $message->message.' '.($message->context['exception'] ?? ''), $logged));

    expect($reported)->toHaveCount(1);

    $exception = $reported[0]->context['exception'];

    expect($reported[0]->level)->toBe('error')
        ->and($exception->getPrevious())->toBeNull()
        ->and($written)->toContain('Passkey ceremony failed with TypeError: VerifyPasskey::__invoke(): Argument #2 ($options) must be of type PublicKeyCredentialRequestOptions, array given')
        ->and($written)->not->toContain($answer['id'])
        ->and($written)->not->toContain($answer['response']['clientDataJSON'])
        ->and($written)->not->toContain($answer['response']['signature'])
        ->and($written)->not->toContain($answer['response']['userHandle'])
        ->and($written)->not->toContain(substr($options['challenge'], 0, 10));
});
