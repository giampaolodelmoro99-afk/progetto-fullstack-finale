<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Notifications\RecoveryCodeUsedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;

/**
 * Password step of the login: the next requests carry the cookie of the
 * session with the pending challenge, as the browser does.
 */
function startLoginChallenge(string $email = 'davide.conti@esempio.it'): void
{
    fromFrontend()->withCredentials()->postJson('/api/v1/login', ['email' => $email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('two_factor_required', true);

    test()->withCookie(config('session.cookie'), session()->getId());
    forgetGuards();
}

beforeEach(function () {
    Sleep::fake();
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

it('completes the login with the code of the authenticator app', function () {
    $user = userWithTwoFactor(['name' => 'Davide Conti', 'email' => 'davide.conti@esempio.it']);
    startLoginChallenge();
    $challengeSessionId = session()->getId();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Davide Conti')
        ->assertJsonPath('data.email', 'davide.conti@esempio.it')
        ->assertJsonMissingPath('meta')
        ->assertSessionMissing('login.pending_user_id')
        ->assertSessionMissing('login.pending_at');

    $this->assertAuthenticatedAs($user, 'web');

    expect(session()->getId())->not->toBe($challengeSessionId)
        ->and(SecurityEvent::sole()->type)->toBe(SecurityEventType::Login)
        ->and($user->fresh()->two_factor_last_used_step)->toBe(59694780);
});

it('keeps the user logged in on the next requests', function () {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])->assertOk();
    $this->withCookie(config('session.cookie'), session()->getId());
    forgetGuards();

    fromFrontend()->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'davide.conti@esempio.it');
});

it('completes the login with a recovery code and signals the codes left', function () {
    $user = userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'recovery_code', 'code' => 'ab12-cd34'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('meta.recovery_codes_remaining', 7)
        ->assertJsonPath('meta.recovery_codes_low', false);

    $this->assertAuthenticatedAs($user, 'web');

    expect($user->fresh()->two_factor_recovery_codes)->toHaveCount(7)
        ->and(SecurityEvent::orderBy('id')->pluck('type')->all())->toBe([SecurityEventType::RecoveryCodeUsed, SecurityEventType::Login]);
    Notification::assertSentTo($user, RecoveryCodeUsedNotification::class);
});

it('signals when two recovery codes or fewer are left', function () {
    userWithTwoFactor([
        'email' => 'davide.conti@esempio.it',
        'two_factor_recovery_codes' => [Hash::make('AAAA-BBBB'), Hash::make('CCCC-DDDD'), Hash::make('EEEE-FFFF')],
    ]);
    startLoginChallenge();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'recovery_code', 'code' => 'EEEE-FFFF'])
        ->assertOk()
        ->assertJsonPath('meta.recovery_codes_remaining', 2)
        ->assertJsonPath('meta.recovery_codes_low', true);
});

it('accepts a recovery code only once', function () {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();
    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'recovery_code', 'code' => 'AB12-CD34'])->assertOk();

    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();
    startLoginChallenge();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'recovery_code', 'code' => 'AB12-CD34'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Codice non valido.');

    $this->assertGuest('web');
});

it('rejects a code of the authenticator app already used', function () {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    $code = totpCode(totpSecret());
    startLoginChallenge();
    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => $code])->assertOk();

    fromFrontend()->postJson('/api/v1/logout')->assertNoContent();
    forgetGuards();
    startLoginChallenge();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => $code])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Codice non valido.');

    $this->assertGuest('web');
});

it('rejects a wrong code on the code field, keeps the challenge and records the attempt', function (string $method, string $code) {
    $user = userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => $method, 'code' => $code])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Codice non valido.',
            'errors' => ['code' => ['Codice non valido.']],
        ])
        ->assertSessionHas('login.pending_user_id', $user->id);

    $this->assertGuest('web');

    $event = SecurityEvent::sole();

    expect($event->user_id)->toBe($user->id)
        ->and($event->type)->toBe(SecurityEventType::TwoFactorChallengeFailed)
        ->and($event->metadata)->toEqual(['context' => 'login', 'method' => $method])
        ->and(json_encode($event->getAttributes()))->not->toContain($code);

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])->assertOk();
})->with([
    'totp' => ['totp', '000000'],
    'recovery code' => ['recovery_code', 'ZZZZ-ZZZZ'],
]);

it('rejects a missing challenge', function () {
    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => '123456'])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'La verifica è scaduta: accedi di nuovo.',
            'code' => 'two_factor_challenge_expired',
        ]);
});

it('keeps the challenge valid for 300 seconds', function () {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();
    $this->travel(300)->seconds();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertOk();
});

it('rejects an expired challenge and drops it', function () {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();
    $this->travel(301)->seconds();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'two_factor_challenge_expired')
        ->assertJsonMissingPath('errors')
        ->assertSessionMissing('login.pending_user_id');

    $this->assertGuest('web');
    expect(SecurityEvent::count())->toBe(0);
});

it('rejects the challenge of a user who no longer has two-factor authentication', function () {
    $user = userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'two_factor_challenge_expired');

    $this->assertGuest('web');
});

it('validates the fields', function (array $data, string $field) {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();

    fromFrontend()->postJson('/api/v1/login/two-factor', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing method' => [['code' => '123456'], 'method'],
    'unknown method' => [['method' => 'sms', 'code' => '123456'], 'method'],
    'passkey without its answer' => [['method' => 'passkey'], 'credential'],
    'passkey answer that is not an object' => [['method' => 'passkey', 'credential' => 'abc'], 'credential'],
    'missing code' => [['method' => 'totp'], 'code'],
    'code of 65 characters' => [['method' => 'totp', 'code' => str_repeat('1', 65)], 'code'],
]);

it('never returns the two-factor secrets', function () {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();

    $response = fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertOk();

    expect($response->getContent())->not->toContain(totpSecret())
        ->not->toContain('two_factor')
        ->not->toContain('recovery');
});

it('limits the attempts to 5 per minute for each user, also from a new session', function () {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    startLoginChallenge();

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => '000000'])
            ->assertUnprocessable();
    }

    $firstSessionId = session()->getId();
    startLoginChallenge();

    expect(session()->getId())->not->toBe($firstSessionId);

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('message', 'Troppi tentativi. Riprova tra 60 secondi.');

    $this->assertGuest('web');
});

it('limits the attempts without a challenge to 5 per minute for each session', function () {
    $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('e', 40));

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'two_factor_challenge_expired');
    }

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => '123456'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
});

it('counts the attempts of each user separately', function () {
    userWithTwoFactor(['email' => 'davide.conti@esempio.it']);
    userWithTwoFactor(['email' => 'giulia.ferri@esempio.it']);
    startLoginChallenge();

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => '000000'])
            ->assertUnprocessable();
    }

    startLoginChallenge('giulia.ferri@esempio.it');

    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => totpCode(totpSecret())])
        ->assertOk();
});
