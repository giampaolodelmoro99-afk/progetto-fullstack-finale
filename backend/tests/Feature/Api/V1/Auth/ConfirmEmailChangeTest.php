<?php

use App\Enums\SecurityEventType;
use App\Models\EmailChangeRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\EmailChangedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

/**
 * A pending request of Giulia to giulia.nuova@esempio.it, created now, whose
 * plain confirmation token is token-di-conferma.
 */
function pendingEmailChange(array $attributes = []): EmailChangeRequest
{
    $user = User::factory()->unverified()->create(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it']);

    return EmailChangeRequest::factory()->for($user)->create([
        'new_email' => 'giulia.nuova@esempio.it',
        'token_hash' => hash('sha256', 'token-di-conferma'),
        'cancel_token_hash' => hash('sha256', 'token-di-annullamento'),
        ...$attributes,
    ]);
}

it('confirms the new address from a guest', function () {
    $request = pendingEmailChange();

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertOk()
        ->assertExactJson(['data' => ['email' => 'giulia.nuova@esempio.it']]);

    $user = $request->user->fresh();

    expect($user->email)->toBe('giulia.nuova@esempio.it')
        ->and($user->email_verified_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and($request->fresh()->confirmed_at->toDateTimeString())->toBe('2026-10-01 10:30:00');
    $this->assertGuest('web');
});

it('confirms the new address from an authenticated user', function () {
    $request = pendingEmailChange();
    actingAsCustomer($request->user);

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertOk();
    actingAsCustomer($request->user->fresh());

    fromFrontend()->getJson('/api/v1/me')
        ->assertJsonPath('data.email', 'giulia.nuova@esempio.it')
        ->assertJsonPath('data.pending_email_change', null);
});

it('records the event and sends the notice to the old address', function () {
    $request = pendingEmailChange();

    fromFrontend()->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36')
        ->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertOk();

    $event = SecurityEvent::sole();

    expect($event->user_id)->toBe($request->user_id)
        ->and($event->type)->toBe(SecurityEventType::EmailChanged);
    Notification::assertSentOnDemand(
        EmailChangedNotification::class,
        fn (EmailChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'giulia.ferri@esempio.it'
            && $notification->name === 'Giulia Ferri'
            && $notification->newEmail === 'giulia.nuova@esempio.it'
            && $notification->device === 'Chrome su Android',
    );
    Notification::assertSentTimes(EmailChangedNotification::class, 1);
});

it('drops the password reset tokens of the old address', function () {
    pendingEmailChange();
    DB::table('password_reset_tokens')->insert(['email' => 'giulia.ferri@esempio.it', 'token' => 'hash', 'created_at' => now()]);
    DB::table('password_reset_tokens')->insert(['email' => 'davide.conti@esempio.it', 'token' => 'hash', 'created_at' => now()]);

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])->assertOk();

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'giulia.ferri@esempio.it']);
    $this->assertDatabaseHas('password_reset_tokens', ['email' => 'davide.conti@esempio.it']);
});

it('accepts the link for 60 minutes and rejects it after 61', function (int $minutes, int $status) {
    pendingEmailChange();
    $this->travel($minutes)->minutes();

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertStatus($status);
})->with([
    '59 minutes' => [59, 200],
    '61 minutes' => [61, 422],
]);

it('rejects an invalid token without changes', function (string $token, ?string $state) {
    $request = pendingEmailChange($state === null ? [] : [$state => now()->subMinute()]);

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => $token])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Il link per cambiare l\'indirizzo email non è valido o è scaduto.',
            'code' => 'invalid_email_change_token',
        ]);

    expect($request->user->fresh()->email)->toBe('giulia.ferri@esempio.it')
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'wrong token' => ['token-sbagliato', null],
    'cancel token' => ['token-di-annullamento', null],
    'expired request' => ['token-di-conferma', 'expires_at'],
    'cancelled request' => ['token-di-conferma', 'cancelled_at'],
]);

it('accepts the token only once', function () {
    pendingEmailChange();

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])->assertOk();

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_email_change_token');

    expect(SecurityEvent::count())->toBe(1);
    Notification::assertSentTimes(EmailChangedNotification::class, 1);
});

it('answers 409 and cancels the request when the address was taken in the meantime', function () {
    $request = pendingEmailChange();
    User::factory()->create(['email' => 'giulia.nuova@esempio.it']);

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertConflict()
        ->assertExactJson([
            'message' => 'Questo indirizzo email è già in uso.',
            'code' => 'email_change_unavailable',
        ]);

    expect($request->user->fresh()->email)->toBe('giulia.ferri@esempio.it')
        ->and($request->fresh()->cancelled_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and($request->fresh()->confirmed_at)->toBeNull()
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_email_change_token');
});

it('answers 409 when the unique index stops a registration committed in between', function () {
    $request = pendingEmailChange();
    User::updating(fn () => throw new UniqueConstraintViolationException('mysql', 'update users', [], new PDOException('Duplicate entry')));

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertConflict()
        ->assertJsonPath('code', 'email_change_unavailable');

    expect($request->user->fresh()->email)->toBe('giulia.ferri@esempio.it')
        ->and($request->fresh()->cancelled_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and($request->fresh()->confirmed_at)->toBeNull()
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
});

it('rejects a request cancelled between the read and the lock', function () {
    $request = pendingEmailChange();
    User::retrieved(fn () => DB::table('email_change_requests')->update(['cancelled_at' => '2026-10-01 10:29:00']));

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_email_change_token');

    expect(DB::table('users')->where('id', $request->user_id)->value('email'))->toBe('giulia.ferri@esempio.it')
        ->and($request->fresh()->confirmed_at)->toBeNull()
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
});

it('requires the token', function () {
    fromFrontend()->postJson('/api/v1/email-change/confirm', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});
