<?php

use App\Enums\SecurityEventType;
use App\Models\Passkey;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Sleep;

/*
| The passkey as second factor, when two-factor authentication is active:
| at the login, in the password reset, in the identity confirmation and in
| the password change, with the options of each context.
*/

beforeEach(function () {
    fakePasskeys();
    Sleep::fake();
    Notification::fake();
    $this->travelTo('2026-10-05 10:00:00');
});

/**
 * Davide Conti with TOTP and the passkey "MacBook di Davide".
 */
function davideWithPasskey(): User
{
    $user = userWithTwoFactor(['name' => 'Davide Conti', 'email' => 'davide.conti@esempio.it']);
    passkeyFor($user, 'MacBook di Davide', 'bWFjYm9vay1kYXZpZGU');

    return $user;
}

function davidePasskeyAnswer(bool $valid = true): array
{
    return passkeyAnswer('bWFjYm9vay1kYXZpZGU', $valid);
}

/**
 * Password step of the login; the next requests carry the session of the
 * pending challenge.
 */
function startPasskeyChallenge(): void
{
    fromFrontend()->withCredentials()->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('methods', ['totp', 'recovery_code', 'passkey']);

    test()->withCookie(config('session.cookie'), session()->getId());
    forgetGuards();
}

function passkeyResetData(string $token, array $extra = []): array
{
    return [
        'email' => 'davide.conti@esempio.it',
        'token' => $token,
        'password' => 'Montagna2026',
        'password_confirmation' => 'Montagna2026',
        ...$extra,
    ];
}

function passkeyNewPassword(array $extra = []): array
{
    return [
        'current_password' => 'password',
        'password' => 'Montagna2026',
        'password_confirmation' => 'Montagna2026',
        ...$extra,
    ];
}

function invalidPasskeySecondFactor(): array
{
    return [
        'message' => 'Passkey non valida: riprova.',
        'errors' => ['credential' => ['Passkey non valida: riprova.']],
    ];
}

function passkeyUnavailable(): array
{
    return [
        'message' => 'Nessuna passkey disponibile per questa verifica.',
        'code' => 'passkey_unavailable',
    ];
}

describe('login', function () {
    it('completes the login with the passkey and names it in the event', function () {
        $user = davideWithPasskey();
        startPasskeyChallenge();
        fromFrontend()->postJson('/api/v1/login/two-factor/passkey-options')->assertOk()->assertJsonStructure(['data' => ['options' => ['challenge']]]);

        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'passkey', 'credential' => davidePasskeyAnswer()])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('meta')
            ->assertSessionMissing('login.pending_user_id');

        $this->assertAuthenticatedAs($user, 'web');

        $event = SecurityEvent::sole();

        expect($event->type)->toBe(SecurityEventType::Login)
            ->and($event->metadata)->toBe(['passkey_name' => 'MacBook di Davide'])
            ->and($user->passkeys()->sole()->last_used_at->toIso8601String())->toBe('2026-10-05T10:00:00+02:00');
    });

    it('rejects a wrong passkey on the credential field, keeps the challenge and records the attempt', function () {
        $user = davideWithPasskey();
        startPasskeyChallenge();
        fromFrontend()->postJson('/api/v1/login/two-factor/passkey-options')->assertOk();

        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'passkey', 'credential' => davidePasskeyAnswer(valid: false)])
            ->assertUnprocessable()
            ->assertExactJson(invalidPasskeySecondFactor())
            ->assertSessionHas('login.pending_user_id', $user->id);

        $this->assertGuest('web');

        $event = SecurityEvent::sole();

        expect($event->type)->toBe(SecurityEventType::TwoFactorChallengeFailed)
            ->and($event->metadata)->toEqual(['context' => 'login', 'method' => 'passkey']);

        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])->assertOk();
    });

    it('rejects the passkey of another user', function () {
        davideWithPasskey();
        passkeyFor(User::factory()->create(), 'Passkey di Giulia', 'cGFzc2tleS1naXVsaWE');
        startPasskeyChallenge();
        fromFrontend()->postJson('/api/v1/login/two-factor/passkey-options')->assertOk();

        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'passkey', 'credential' => passkeyAnswer('cGFzc2tleS1naXVsaWE')])
            ->assertUnprocessable()
            ->assertExactJson(invalidPasskeySecondFactor());

        $this->assertGuest('web');
    });

    it('rejects the passkey without the options of the login', function () {
        davideWithPasskey();
        startPasskeyChallenge();

        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'passkey', 'credential' => davidePasskeyAnswer()])
            ->assertUnprocessable()
            ->assertExactJson(invalidPasskeySecondFactor());

        $this->assertGuest('web');
    });

    it('rejects the options of the passwordless login', function () {
        davideWithPasskey();
        startPasskeyChallenge();
        fromFrontend()->postJson('/api/v1/passkeys/login/options')->assertOk();

        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'passkey', 'credential' => davidePasskeyAnswer()])
            ->assertUnprocessable()
            ->assertExactJson(invalidPasskeySecondFactor());
    });

    it('answers the options only within a challenge', function () {
        davideWithPasskey();

        fromFrontend()->postJson('/api/v1/login/two-factor/passkey-options')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'La verifica è scaduta: accedi di nuovo.',
                'code' => 'two_factor_challenge_expired',
            ]);
    });

    it('answers no options to a user of the challenge without passkeys', function () {
        userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
        fromFrontend()->withCredentials()->postJson('/api/v1/login', ['email' => 'davide.conti@esempio.it', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('methods', ['totp', 'recovery_code']);
        $this->withCookie(config('session.cookie'), session()->getId());

        fromFrontend()->postJson('/api/v1/login/two-factor/passkey-options')
            ->assertUnprocessable()
            ->assertExactJson(passkeyUnavailable());
    });
});

describe('password reset', function () {
    it('resets the password with the passkey', function () {
        $user = davideWithPasskey();
        $token = Password::broker()->createToken($user);
        fromFrontend()->postJson('/api/v1/reset-password/passkey-options', ['email' => 'davide.conti@esempio.it', 'token' => $token])->assertOk();

        fromFrontend()->postJson('/api/v1/reset-password', passkeyResetData($token, ['method' => 'passkey', 'credential' => davidePasskeyAnswer()]))
            ->assertOk()
            ->assertExactJson(['message' => 'La password è stata reimpostata: accedi con la nuova password.']);

        expect(Hash::check('Montagna2026', $user->fresh()->password))->toBeTrue();
    });

    it('rejects a wrong passkey and keeps the password', function () {
        $user = davideWithPasskey();
        $token = Password::broker()->createToken($user);
        fromFrontend()->postJson('/api/v1/reset-password/passkey-options', ['email' => 'davide.conti@esempio.it', 'token' => $token])->assertOk();

        fromFrontend()->postJson('/api/v1/reset-password', passkeyResetData($token, ['method' => 'passkey', 'credential' => davidePasskeyAnswer(valid: false)]))
            ->assertUnprocessable()
            ->assertExactJson(invalidPasskeySecondFactor());

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
            ->and(SecurityEvent::sole()->metadata)->toEqual(['context' => 'password_reset', 'method' => 'passkey']);
    });

    it('requires the answer of the passkey', function () {
        $user = davideWithPasskey();
        $token = Password::broker()->createToken($user);

        fromFrontend()->postJson('/api/v1/reset-password', passkeyResetData($token, ['method' => 'passkey']))
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Il campo passkey è obbligatorio.',
                'errors' => ['credential' => ['Il campo passkey è obbligatorio.']],
            ]);

        expect(SecurityEvent::count())->toBe(0);
    });

    it('checks the token before answering the options', function () {
        davideWithPasskey();

        fromFrontend()->postJson('/api/v1/reset-password/passkey-options', ['email' => 'davide.conti@esempio.it', 'token' => 'sbagliato'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_reset_token');
    });

    it('validates the fields of the options', function (array $data, string $field) {
        fromFrontend()->postJson('/api/v1/reset-password/passkey-options', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'missing email' => [['token' => 'abc'], 'email'],
        'missing token' => [['email' => 'davide.conti@esempio.it'], 'token'],
    ]);
});

describe('identity confirmation', function () {
    it('confirms the identity with password and passkey', function () {
        actingAsCustomer(davideWithPasskey());
        fromFrontend()->postJson('/api/v1/me/confirm-identity/passkey-options')->assertOk();

        fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'passkey', 'credential' => davidePasskeyAnswer()])
            ->assertOk()
            ->assertExactJson(['data' => ['confirmed_until' => '2026-10-05T10:10:00+02:00']])
            ->assertSessionHas('auth.identity_confirmed_at', 1791187200);
    });

    it('rejects a wrong passkey', function () {
        actingAsCustomer(davideWithPasskey());
        fromFrontend()->postJson('/api/v1/me/confirm-identity/passkey-options')->assertOk();

        fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'passkey', 'credential' => davidePasskeyAnswer(valid: false)])
            ->assertUnprocessable()
            ->assertExactJson(invalidPasskeySecondFactor())
            ->assertSessionMissing('auth.identity_confirmed_at');
    });

    it('requires the answer of the passkey without counting an attempt', function () {
        actingAsCustomer(davideWithPasskey());

        fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'passkey'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.credential.0', 'Il campo passkey è obbligatorio.')
            ->assertJsonMissingPath('errors.code');

        expect(SecurityEvent::count())->toBe(0);
    });

    it('rejects the options of the password change', function () {
        actingAsCustomer(davideWithPasskey());
        fromFrontend()->postJson('/api/v1/me/password/passkey-options')->assertOk();

        fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password', 'method' => 'passkey', 'credential' => davidePasskeyAnswer()])
            ->assertUnprocessable()
            ->assertExactJson(invalidPasskeySecondFactor());
    });
});

describe('password change', function () {
    it('changes the password with the passkey in the form', function () {
        $user = actingAsCustomer(davideWithPasskey());
        fromFrontend()->postJson('/api/v1/me/password/passkey-options')->assertOk();

        fromFrontend()->putJson('/api/v1/me/password', passkeyNewPassword(['method' => 'passkey', 'credential' => davidePasskeyAnswer()]))
            ->assertOk()
            ->assertExactJson(['data' => ['password_changed_at' => '2026-10-05T10:00:00+02:00']]);

        expect(Hash::check('Montagna2026', $user->fresh()->password))->toBeTrue();
    });

    it('rejects a wrong passkey and keeps the password', function () {
        $user = actingAsCustomer(davideWithPasskey());
        fromFrontend()->postJson('/api/v1/me/password/passkey-options')->assertOk();

        fromFrontend()->putJson('/api/v1/me/password', passkeyNewPassword(['method' => 'passkey', 'credential' => davidePasskeyAnswer(valid: false)]))
            ->assertUnprocessable()
            ->assertExactJson(invalidPasskeySecondFactor());

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });

    it('requires the answer of the passkey', function () {
        actingAsCustomer(davideWithPasskey());

        fromFrontend()->putJson('/api/v1/me/password', passkeyNewPassword(['method' => 'passkey']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.credential.0', 'Il campo passkey è obbligatorio.');
    });
});

describe('options of the authenticated contexts', function () {
    it('rejects a guest', function (string $uri) {
        fromFrontend()->postJson($uri)->assertUnauthorized();
    })->with([
        '/api/v1/me/confirm-identity/passkey-options',
        '/api/v1/me/password/passkey-options',
    ]);

    it('answers the options to a user with two-factor authentication and a passkey', function (string $uri) {
        actingAsCustomer(davideWithPasskey());

        fromFrontend()->postJson($uri)
            ->assertOk()
            ->assertJsonStructure(['data' => ['options' => ['challenge']]]);
    })->with([
        '/api/v1/me/confirm-identity/passkey-options',
        '/api/v1/me/password/passkey-options',
    ]);

    it('answers no options without a passkey or without two-factor authentication', function (string $uri, string $user) {
        actingAsCustomer(match ($user) {
            'two-factor without passkey' => userWithTwoFactor(),
            'passkey without two-factor' => User::factory()->withPasskey()->create(),
        });

        fromFrontend()->postJson($uri)
            ->assertUnprocessable()
            ->assertExactJson(passkeyUnavailable());
    })->with([
        '/api/v1/me/confirm-identity/passkey-options',
        '/api/v1/me/password/passkey-options',
    ])->with([
        'two-factor without passkey',
        'passkey without two-factor',
    ]);

    it('shares the identity confirmation limiter', function () {
        actingAsCustomer(davideWithPasskey());

        foreach (range(1, 5) as $attempt) {
            fromFrontend()->postJson('/api/v1/me/password/passkey-options')->assertOk();
        }

        fromFrontend()->postJson('/api/v1/me/confirm-identity/passkey-options')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60');
    });
});

it('counts the wrong passkeys in the counter shared by every context', function () {
    $user = davideWithPasskey();
    $token = Password::broker()->createToken($user);

    foreach (range(1, 3) as $attempt) {
        fromFrontend()->postJson('/api/v1/reset-password/passkey-options', ['email' => 'davide.conti@esempio.it', 'token' => $token])->assertOk();
        fromFrontend()->postJson('/api/v1/reset-password', passkeyResetData($token, ['method' => 'passkey', 'credential' => davidePasskeyAnswer(valid: false)]))
            ->assertUnprocessable();
    }

    startPasskeyChallenge();

    foreach (range(1, 2) as $attempt) {
        fromFrontend()->postJson('/api/v1/login/two-factor/passkey-options')->assertOk();
        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'passkey', 'credential' => davidePasskeyAnswer(valid: false)])
            ->assertUnprocessable();
    }

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');

    $this->assertGuest('web');
    expect(SecurityEvent::query()->where('type', SecurityEventType::TwoFactorChallengeFailed)->count())->toBe(5);
});

it('never shows the stored credential in the options', function () {
    actingAsCustomer(davideWithPasskey());
    $credential = Passkey::sole()->credential;

    $content = fromFrontend()->postJson('/api/v1/me/confirm-identity/passkey-options')->assertOk()->getContent();

    expect($content)->not->toContain($credential['credentialPublicKey'])
        ->not->toContain($credential['userHandle']);
});
