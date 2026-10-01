<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\RecoveryCodeUsedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

function newPassword(array $extra = []): array
{
    return [
        'current_password' => 'password',
        'password' => 'Montagna2026',
        'password_confirmation' => 'Montagna2026',
        ...$extra,
    ];
}

it('rejects a guest', function () {
    fromFrontend()->putJson('/api/v1/me/password', newPassword())
        ->assertUnauthorized();
});

it('changes the password with the current one without two-factor authentication', function () {
    $user = actingAsCustomer(User::factory()->create(['password_changed_at' => '2026-01-10 09:00:00']));

    fromFrontend()->putJson('/api/v1/me/password', newPassword())
        ->assertOk()
        ->assertExactJson(['data' => ['password_changed_at' => '2026-10-01T10:30:00+02:00']]);

    $user->refresh();

    expect(Hash::check('Montagna2026', $user->password))->toBeTrue()
        ->and($user->password_changed_at->toDateTimeString())->toBe('2026-10-01 10:30:00');
});

it('records the event and sends the notice', function () {
    $user = actingAsVerifiedCustomer();

    fromFrontend()->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36')
        ->putJson('/api/v1/me/password', newPassword())
        ->assertOk();

    $event = SecurityEvent::sole();

    expect($event->user_id)->toBe($user->id)
        ->and($event->type)->toBe(SecurityEventType::PasswordChanged);
    Notification::assertSentTo($user, PasswordChangedNotification::class, fn (PasswordChangedNotification $notification) => $notification->device === 'Chrome su macOS'
        && $notification->occurredAt->toDateTimeString() === '2026-10-01 10:30:00');
});

it('rejects a wrong current password without changes', function () {
    $user = actingAsVerifiedCustomer();

    fromFrontend()->putJson('/api/v1/me/password', newPassword(['current_password' => 'sbagliata']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.current_password.0', 'La password non è corretta.');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
});

it('validates the new password', function (array $data, string $field) {
    actingAsVerifiedCustomer();

    fromFrontend()->putJson('/api/v1/me/password', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'current password missing' => [['password' => 'Montagna2026', 'password_confirmation' => 'Montagna2026'], 'current_password'],
    'new password missing' => [['current_password' => 'password'], 'password'],
    'confirmation different' => [['current_password' => 'password', 'password' => 'Montagna2026', 'password_confirmation' => 'Montagna2027'], 'password'],
    '9 characters' => [['current_password' => 'password', 'password' => 'Monta2026', 'password_confirmation' => 'Monta2026'], 'password'],
    'without numbers' => [['current_password' => 'password', 'password' => 'MontagnaAlta', 'password_confirmation' => 'MontagnaAlta'], 'password'],
    'unknown method' => [['current_password' => 'password', 'password' => 'Montagna2026', 'password_confirmation' => 'Montagna2026', 'method' => 'sms', 'code' => '123456'], 'method'],
]);

it('requires the second factor with two-factor authentication', function () {
    $user = actingAsCustomer(userWithTwoFactor());

    fromFrontend()->putJson('/api/v1/me/password', newPassword())
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Il campo codice è obbligatorio.');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and(SecurityEvent::count())->toBe(0);
});

it('changes the password with the code of the authenticator app', function () {
    $user = actingAsCustomer(userWithTwoFactor());

    fromFrontend()->putJson('/api/v1/me/password', newPassword(['method' => 'totp', 'code' => totpCode(totpSecret())]))
        ->assertOk()
        ->assertExactJson(['data' => ['password_changed_at' => '2026-10-01T10:30:00+02:00']]);

    expect(Hash::check('Montagna2026', $user->fresh()->password))->toBeTrue();
});

it('changes the password with a recovery code and signals the codes left', function () {
    $user = actingAsCustomer(userWithTwoFactor());

    fromFrontend()->putJson('/api/v1/me/password', newPassword(['method' => 'recovery_code', 'code' => 'AB12-CD34']))
        ->assertOk()
        ->assertExactJson([
            'data' => ['password_changed_at' => '2026-10-01T10:30:00+02:00'],
            'meta' => ['recovery_codes_remaining' => 7, 'recovery_codes_low' => false],
        ]);

    expect($user->fresh()->two_factor_recovery_codes)->toHaveCount(7);
    Notification::assertSentTo($user, RecoveryCodeUsedNotification::class);
    Notification::assertSentTo($user, PasswordChangedNotification::class);
});

it('rejects a wrong code, records the attempt and keeps the password', function () {
    $user = actingAsCustomer(userWithTwoFactor());

    fromFrontend()->putJson('/api/v1/me/password', newPassword(['method' => 'totp', 'code' => '000000']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Codice non valido.');

    $event = SecurityEvent::sole();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
        ->and($event->type)->toBe(SecurityEventType::TwoFactorChallengeFailed)
        ->and($event->metadata)->toEqual(['context' => 'password_change', 'method' => 'totp']);
    Notification::assertNotSentTo($user, PasswordChangedNotification::class);
});

it('closes the other sessions and keeps the current one', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $other = User::factory()->create();
    insertSession('other-browser', $user);
    insertSession('another-user', $other);
    loginThroughApi('giulia.ferri@esempio.it');

    fromFrontend()->putJson('/api/v1/me/password', newPassword())->assertOk();
    $this->withCookie(config('session.cookie'), session()->getId());
    forgetGuards();

    fromFrontend()->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'giulia.ferri@esempio.it');

    expect(DB::table('sessions')->where('id', 'other-browser')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'another-user')->exists())->toBeTrue();
});

it('regenerates the session and drops the identity confirmation', function () {
    actingAsVerifiedCustomer();
    confirmIdentity();
    $before = session()->getId();

    fromFrontend()->putJson('/api/v1/me/password', newPassword())
        ->assertOk()
        ->assertSessionMissing('auth.identity_confirmed_at');

    expect(session()->getId())->not->toBe($before);
});

it('changes the remember token', function () {
    $user = actingAsCustomer(User::factory()->create(['remember_token' => 'vecchio-token']));

    fromFrontend()->putJson('/api/v1/me/password', newPassword())->assertOk();

    expect($user->fresh()->remember_token)->not->toBe('vecchio-token');
});

it('limits the attempts to 5 per minute for each user', function () {
    actingAsVerifiedCustomer();

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->putJson('/api/v1/me/password', newPassword(['current_password' => 'sbagliata']))
            ->assertUnprocessable();
    }

    fromFrontend()->putJson('/api/v1/me/password', newPassword())
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
});

it('shares the identity confirmation limiter', function () {
    actingAsVerifiedCustomer();

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'sbagliata'])
            ->assertUnprocessable();
    }

    fromFrontend()->putJson('/api/v1/me/password', newPassword())
        ->assertTooManyRequests();
});

it('shares the count of wrong codes with the other contexts', function () {
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

    fromFrontend()->putJson('/api/v1/me/password', newPassword(['method' => 'totp', 'code' => totpCode(totpSecret())]))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('never returns the password or the two-factor secrets', function () {
    actingAsCustomer(userWithTwoFactor());

    $response = fromFrontend()->putJson('/api/v1/me/password', newPassword(['method' => 'totp', 'code' => totpCode(totpSecret())]))
        ->assertOk();

    expect($response->getContent())->not->toContain('Montagna2026')
        ->not->toContain(totpSecret());
});
