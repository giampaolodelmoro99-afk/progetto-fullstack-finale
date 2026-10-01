<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

function resetData(string $token, array $overrides = []): array
{
    return array_merge([
        'email' => 'giulia.ferri@esempio.it',
        'token' => $token,
        'password' => 'Montagna2026',
        'password_confirmation' => 'Montagna2026',
    ], $overrides);
}

beforeEach(function () {
    Notification::fake();
});

it('resets the password', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it', 'password_changed_at' => '2026-01-10 09:00:00']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token))
        ->assertOk()
        ->assertExactJson(['message' => 'La password è stata reimpostata: accedi con la nuova password.']);

    $user->refresh();

    expect(Hash::check('Montagna2026', $user->password))->toBeTrue()
        ->and($user->password_changed_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
    $this->assertGuest('web');
});

it('closes every session of the user and keeps those of other users', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $other = User::factory()->create();
    insertSession('sessione-telefono', $user);
    insertSession('sessione-portatile', $user);
    insertSession('sessione-altro-utente', $other);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token))->assertOk();

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['sessione-altro-utente']);
});

it('sends the notice with date, time and device, and records the event', function () {
    $this->travelTo('2026-10-01 16:52:00');
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()
        ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36')
        ->postJson('/api/v1/reset-password', resetData($token))
        ->assertOk();

    Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $notification) {
        expect($notification->device)->toBe('Chrome su macOS')
            ->and($notification->resetAt->toDateTimeString())->toBe('2026-10-01 16:52:00');

        return true;
    });

    $event = SecurityEvent::sole();
    expect($event->user_id)->toBe($user->id)
        ->and($event->type)->toBe(SecurityEventType::PasswordReset);
});

it('changes nothing and sends nothing when the security event cannot be written', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    insertSession('sessione-telefono', $user);
    $token = Password::broker()->createToken($user);
    SecurityEvent::creating(fn () => throw new RuntimeException('Scrittura non riuscita'));

    $this->withoutExceptionHandling();

    expect(fn () => fromFrontend()->postJson('/api/v1/reset-password', resetData($token)))
        ->toThrow(RuntimeException::class, 'Scrittura non riuscita');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->count())->toBe(1)
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['sessione-telefono']);
    Notification::assertNothingSent();
});

it('rejects an invalid or expired token without changing the password', function (string $case) {
    $this->travelTo('2026-10-01 10:30:00');
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    if ($case === 'expired after 61 minutes') {
        $this->travelTo('2026-10-01 11:31:00');
    }

    fromFrontend()->postJson('/api/v1/reset-password', resetData($case === 'wrong token' ? 'token-sbagliato' : $token))
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Il link per reimpostare la password non è valido o è scaduto.',
            'code' => 'invalid_reset_token',
        ]);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    Notification::assertNothingSent();
})->with(['wrong token', 'expired after 61 minutes']);

it('accepts a token only once', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token))->assertOk();

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token, ['password' => 'Collina2026', 'password_confirmation' => 'Collina2026']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_reset_token');
});

it('requires the second factor when two-factor authentication is active', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token))
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Il campo codice è obbligatorio.')
        ->assertJsonMissingPath('code');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->count())->toBe(1);
});

it('rejects a wrong second factor and records the failed attempt', function (string $method, string $code) {
    $this->travelTo('2026-10-01 10:30:00');
    $user = userWithTwoFactor(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token, ['method' => $method, 'code' => $code]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Codice non valido.')
        ->assertJsonMissingPath('code');

    $event = SecurityEvent::sole();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->count())->toBe(1)
        ->and($event->type)->toBe(SecurityEventType::TwoFactorChallengeFailed)
        ->and($event->metadata)->toEqual(['context' => 'password_reset', 'method' => $method])
        ->and(json_encode($event->getAttributes()))->not->toContain($code);
    Notification::assertNothingSent();
})->with([
    'totp' => ['totp', '000000'],
    'recovery code' => ['recovery_code', 'ZZZZ-ZZZZ'],
]);

it('resets the password with the code of the authenticator app', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = userWithTwoFactor(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token, ['method' => 'totp', 'code' => totpCode(totpSecret())]))
        ->assertOk()
        ->assertExactJson(['message' => 'La password è stata reimpostata: accedi con la nuova password.']);

    expect(Hash::check('Montagna2026', $user->fresh()->password))->toBeTrue()
        ->and(SecurityEvent::where('type', SecurityEventType::PasswordReset)->count())->toBe(1);
    Notification::assertSentTo($user, PasswordResetNotification::class);
});

it('rejects a code of the authenticator app already used at the login', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = userWithTwoFactor(['email' => 'giulia.ferri@esempio.it']);
    $code = totpCode(totpSecret());
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/login', ['email' => 'giulia.ferri@esempio.it', 'password' => 'password'])->assertOk();
    fromFrontend()->postJson('/api/v1/login/two-factor', ['method' => 'totp', 'code' => $code])->assertOk();

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token, ['method' => 'totp', 'code' => $code]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Codice non valido.');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('limits the second factor attempts to 5 per minute for each user', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = userWithTwoFactor(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/reset-password', resetData($token, ['method' => 'totp', 'code' => '000000']))
            ->assertUnprocessable();
    }

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token, ['method' => 'totp', 'code' => totpCode(totpSecret())]))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertJsonPath('message', 'Troppi tentativi. Riprova tra 60 secondi.');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->two_factor_last_used_step)->toBeNull();

    $this->travel(61)->seconds();

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token, ['method' => 'totp', 'code' => totpCode(totpSecret())]))
        ->assertOk();
});

it('resets the password with a recovery code, which is consumed', function () {
    $user = userWithTwoFactor(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token, ['method' => 'recovery_code', 'code' => 'AB12-CD34']))
        ->assertOk()
        ->assertJsonPath('meta.recovery_codes_remaining', 7)
        ->assertJsonPath('meta.recovery_codes_low', false);

    $user->refresh();

    expect(Hash::check('Montagna2026', $user->password))->toBeTrue()
        ->and($user->two_factor_recovery_codes)->toHaveCount(7)
        ->and(SecurityEvent::where('type', SecurityEventType::RecoveryCodeUsed)->count())->toBe(1);
});

it('checks the token before the second factor', function () {
    User::factory()->withTwoFactor()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/reset-password', resetData('token-sbagliato'))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_reset_token')
        ->assertJsonMissingPath('errors');
});

it('rejects invalid data', function (array $overrides, string $field) {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/reset-password', resetData($token, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing token' => [['token' => ''], 'token'],
    'missing email' => [['email' => ''], 'email'],
    'password of 9 characters' => [['password' => 'Montagna2', 'password_confirmation' => 'Montagna2'], 'password'],
    'password without numbers' => [['password' => 'MontagnaAlta', 'password_confirmation' => 'MontagnaAlta'], 'password'],
    'different confirmation' => [['password_confirmation' => 'Montagna2027'], 'password'],
    'unknown method' => [['method' => 'sms', 'code' => '123456'], 'method'],
]);
