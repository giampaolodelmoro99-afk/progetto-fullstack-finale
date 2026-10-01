<?php

use App\Enums\SecurityEventType;
use App\Models\EmailChangeRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

function emailChangeToCancel(array $attributes = []): EmailChangeRequest
{
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    return EmailChangeRequest::factory()->for($user)->create([
        'new_email' => 'giulia.nuova@esempio.it',
        'token_hash' => hash('sha256', 'token-di-conferma'),
        'cancel_token_hash' => hash('sha256', 'token-di-annullamento'),
        ...$attributes,
    ]);
}

it('cancels the request from the link of the old address, as a guest', function () {
    $request = emailChangeToCancel();

    fromFrontend()->postJson('/api/v1/email-change/cancel', ['token' => 'token-di-annullamento'])
        ->assertNoContent();

    $event = SecurityEvent::sole();

    expect($request->fresh()->cancelled_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and($request->user->fresh()->email)->toBe('giulia.ferri@esempio.it')
        ->and($event->user_id)->toBe($request->user_id)
        ->and($event->type)->toBe(SecurityEventType::EmailChangeCancelled);
    Notification::assertNothingSent();
    $this->assertGuest('web');
});

it('makes the confirmation link useless', function () {
    emailChangeToCancel();

    fromFrontend()->postJson('/api/v1/email-change/cancel', ['token' => 'token-di-annullamento'])->assertNoContent();

    fromFrontend()->postJson('/api/v1/email-change/confirm', ['token' => 'token-di-conferma'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_email_change_token');
});

it('rejects an invalid cancel token', function (string $token, ?string $state) {
    $request = emailChangeToCancel($state === null ? [] : [$state => now()->subMinute()]);

    fromFrontend()->postJson('/api/v1/email-change/cancel', ['token' => $token])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Il link per cambiare l\'indirizzo email non è valido o è scaduto.',
            'code' => 'invalid_email_change_token',
        ]);

    expect(SecurityEvent::count())->toBe(0)
        ->and($request->fresh()->cancelled_at?->toDateTimeString())->toBe($state === 'cancelled_at' ? '2026-10-01 10:29:00' : null);
})->with([
    'wrong token' => ['token-sbagliato', null],
    'confirmation token' => ['token-di-conferma', null],
    'expired request' => ['token-di-annullamento', 'expires_at'],
    'confirmed request' => ['token-di-annullamento', 'confirmed_at'],
    'cancelled request' => ['token-di-annullamento', 'cancelled_at'],
]);

it('accepts the cancel token only once', function () {
    emailChangeToCancel();

    fromFrontend()->postJson('/api/v1/email-change/cancel', ['token' => 'token-di-annullamento'])->assertNoContent();

    fromFrontend()->postJson('/api/v1/email-change/cancel', ['token' => 'token-di-annullamento'])
        ->assertUnprocessable();

    expect(SecurityEvent::count())->toBe(1);
});

it('rejects the cancel link after 61 minutes', function () {
    emailChangeToCancel();
    $this->travel(61)->minutes();

    fromFrontend()->postJson('/api/v1/email-change/cancel', ['token' => 'token-di-annullamento'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_email_change_token');
});

it('rejects a request confirmed between the read and the lock', function () {
    $request = emailChangeToCancel();
    User::retrieved(fn () => DB::table('email_change_requests')->update(['confirmed_at' => '2026-10-01 10:29:00']));

    fromFrontend()->postJson('/api/v1/email-change/cancel', ['token' => 'token-di-annullamento'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_email_change_token');

    expect($request->fresh()->cancelled_at)->toBeNull()
        ->and(SecurityEvent::count())->toBe(0);
});

it('requires the token', function () {
    fromFrontend()->postJson('/api/v1/email-change/cancel', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});
