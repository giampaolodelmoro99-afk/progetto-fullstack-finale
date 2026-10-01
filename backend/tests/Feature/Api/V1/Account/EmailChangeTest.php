<?php

use App\Enums\SecurityEventType;
use App\Models\EmailChangeRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\EmailChangeCancelledNotification;
use App\Notifications\EmailChangeConfirmationNotification;
use App\Notifications\EmailChangeRequestedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

function giulia(array $attributes = []): User
{
    return User::factory()->create(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it', ...$attributes]);
}

it('rejects a guest', function () {
    fromFrontend()->postJson('/api/v1/me/email', ['email' => 'giulia.nuova@esempio.it'])
        ->assertUnauthorized();
});

it('asks for the identity confirmation', function (bool $twoFactor, array $requires) {
    actingAsCustomer($twoFactor ? userWithTwoFactor() : giulia());

    fromFrontend()->postJson('/api/v1/me/email', ['email' => 'giulia.nuova@esempio.it'])
        ->assertStatus(423)
        ->assertJsonPath('requires', $requires);

    expect(EmailChangeRequest::count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'without two-factor authentication' => [false, ['password']],
    'with two-factor authentication' => [true, ['password', 'second_factor']],
]);

it('creates a pending request after the identity confirmation', function () {
    $user = actingAsCustomer(giulia());
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/email', ['email' => '  Giulia.Nuova@Esempio.it '])
        ->assertCreated()
        ->assertExactJson(['data' => [
            'new_email' => 'giulia.nuova@esempio.it',
            'expires_at' => '2026-10-01T11:30:00+02:00',
        ]]);

    $request = EmailChangeRequest::sole();

    expect($request->user_id)->toBe($user->id)
        ->and($request->new_email)->toBe('giulia.nuova@esempio.it')
        ->and($request->confirmed_at)->toBeNull()
        ->and($request->cancelled_at)->toBeNull()
        ->and($user->fresh()->email)->toBe('giulia.ferri@esempio.it');
});

it('stores only the sha-256 hashes of the tokens', function () {
    actingAsCustomer(giulia());
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/email', ['email' => 'giulia.nuova@esempio.it'])->assertCreated();

    $token = null;
    $cancelToken = null;
    Notification::assertSentOnDemand(EmailChangeConfirmationNotification::class, function (EmailChangeConfirmationNotification $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });
    Notification::assertSentTo(User::sole(), EmailChangeRequestedNotification::class, function (EmailChangeRequestedNotification $notification) use (&$cancelToken) {
        $cancelToken = $notification->cancelToken;

        return true;
    });
    $request = EmailChangeRequest::sole();

    expect($token)->toHaveLength(64)
        ->and($cancelToken)->toHaveLength(64)
        ->and($token)->not->toBe($cancelToken)
        ->and($request->token_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($request->cancel_token_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($request->token_hash)->toBe(hash('sha256', $token))
        ->and($request->cancel_token_hash)->toBe(hash('sha256', $cancelToken))
        ->and(json_encode($request->getAttributes()))->not->toContain($token)
        ->not->toContain($cancelToken);
});

it('sends the confirmation link to the new address and the notice to the old one', function () {
    $user = actingAsCustomer(giulia());
    confirmIdentity();

    fromFrontend()->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1')
        ->postJson('/api/v1/me/email', ['email' => 'giulia.nuova@esempio.it'])
        ->assertCreated();

    Notification::assertSentOnDemand(
        EmailChangeConfirmationNotification::class,
        fn (EmailChangeConfirmationNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'giulia.nuova@esempio.it'
            && $notification->name === 'Giulia Ferri',
    );
    Notification::assertSentTo(
        $user,
        EmailChangeRequestedNotification::class,
        fn (EmailChangeRequestedNotification $notification) => $notification->newEmail === 'giulia.nuova@esempio.it'
            && $notification->device === 'Safari su iOS',
    );
});

it('records the event of the request', function () {
    $user = actingAsCustomer(giulia());
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/email', ['email' => 'giulia.nuova@esempio.it'])->assertCreated();

    $event = SecurityEvent::sole();

    expect($event->user_id)->toBe($user->id)
        ->and($event->type)->toBe(SecurityEventType::EmailChangeRequested)
        ->and($event->metadata)->toBeNull();
});

it('cancels the previous pending request', function () {
    $user = actingAsCustomer(giulia());
    $previous = EmailChangeRequest::factory()->for($user)->create(['new_email' => 'giulia.vecchia@esempio.it']);
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/email', ['email' => 'giulia.nuova@esempio.it'])->assertCreated();

    expect($previous->fresh()->cancelled_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and(EmailChangeRequest::pending()->sole()->new_email)->toBe('giulia.nuova@esempio.it');

    fromFrontend()->getJson('/api/v1/me')
        ->assertJsonPath('data.pending_email_change.new_email', 'giulia.nuova@esempio.it');
});

it('rejects an invalid new address', function (string $email, string $message) {
    User::factory()->create(['email' => 'davide.conti@esempio.it']);
    actingAsCustomer(giulia());
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/email', ['email' => $email])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', $message);

    expect(EmailChangeRequest::count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'same address' => ['giulia.ferri@esempio.it', 'Il nuovo indirizzo deve essere diverso da quello attuale.'],
    'same address in upper case' => ['Giulia.Ferri@Esempio.it', 'Il nuovo indirizzo deve essere diverso da quello attuale.'],
    'already registered' => ['davide.conti@esempio.it', 'Questo indirizzo email è già in uso.'],
    'reserved domain' => ['mario@esempio.invalid', 'Inserisci un indirizzo email valido.'],
    'address of a deleted account' => ['deleted-7@anonymized.invalid', 'Inserisci un indirizzo email valido.'],
    'reserved domain in upper case' => ['Mario@Posta.INVALID', 'Inserisci un indirizzo email valido.'],
]);

it('requires a valid email address', function (mixed $email) {
    actingAsCustomer(giulia());
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/email', ['email' => $email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
})->with([
    'missing' => [null],
    'not an address' => ['giulia.nuova'],
    'too long' => [str_repeat('a', 250).'@esempio.it'],
]);

it('accepts an address that only contains invalid before the domain', function () {
    actingAsCustomer(giulia());
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/email', ['email' => 'invalid@esempio.it'])
        ->assertCreated();
});

it('limits the requests to 3 per hour for each user', function () {
    actingAsCustomer(giulia());
    confirmIdentity();

    foreach (range(1, 3) as $attempt) {
        fromFrontend()->postJson('/api/v1/me/email', ['email' => "giulia.nuova{$attempt}@esempio.it"])
            ->assertCreated();
    }

    fromFrontend()->postJson('/api/v1/me/email', ['email' => 'giulia.nuova4@esempio.it'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '3600');
});

it('counts a request without identity confirmation as an attempt', function () {
    actingAsCustomer(giulia());

    foreach (range(1, 3) as $attempt) {
        fromFrontend()->postJson('/api/v1/me/email', ['email' => 'giulia.nuova@esempio.it'])
            ->assertStatus(423);
    }

    fromFrontend()->postJson('/api/v1/me/email', ['email' => 'giulia.nuova@esempio.it'])
        ->assertTooManyRequests();
});

it('cancels the pending request from the profile', function () {
    $user = actingAsCustomer(giulia());
    $request = EmailChangeRequest::factory()->for($user)->create(['new_email' => 'giulia.nuova@esempio.it']);

    fromFrontend()->getJson('/api/v1/me')
        ->assertJsonPath('data.pending_email_change.new_email', 'giulia.nuova@esempio.it');

    fromFrontend()->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0')
        ->deleteJson('/api/v1/me/email-change')
        ->assertNoContent();

    $event = SecurityEvent::sole();

    expect($request->fresh()->cancelled_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and($user->fresh()->email)->toBe('giulia.ferri@esempio.it')
        ->and($event->type)->toBe(SecurityEventType::EmailChangeCancelled)
        ->and($event->user_id)->toBe($user->id);
    Notification::assertSentTo($user, EmailChangeCancelledNotification::class, fn (EmailChangeCancelledNotification $notification) => $notification->device === 'Firefox su Windows');
    Notification::assertSentTimes(EmailChangeCancelledNotification::class, 1);

    fromFrontend()->getJson('/api/v1/me')
        ->assertJsonPath('data.pending_email_change', null);
});

it('does not ask for the identity confirmation to cancel from the profile', function () {
    $user = actingAsCustomer(userWithTwoFactor());
    EmailChangeRequest::factory()->for($user)->create();

    fromFrontend()->deleteJson('/api/v1/me/email-change')
        ->assertNoContent();
});

it('answers 404 without a pending request', function (?string $state) {
    $user = actingAsCustomer(giulia());
    $other = User::factory()->create();
    EmailChangeRequest::factory()->for($other)->create();

    if ($state !== null) {
        EmailChangeRequest::factory()->for($user)->{$state}()->create();
    }

    fromFrontend()->deleteJson('/api/v1/me/email-change')
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);

    expect(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'no request' => [null],
    'expired request' => ['expired'],
    'confirmed request' => ['confirmed'],
    'cancelled request' => ['cancelled'],
]);

it('rejects a guest that cancels from the profile', function () {
    fromFrontend()->deleteJson('/api/v1/me/email-change')
        ->assertUnauthorized();
});

it('limits the cancellations from the profile with the email change limiter', function () {
    $user = actingAsCustomer(giulia());
    confirmIdentity();

    foreach (range(1, 2) as $attempt) {
        fromFrontend()->postJson('/api/v1/me/email', ['email' => "giulia.nuova{$attempt}@esempio.it"])->assertCreated();
    }

    fromFrontend()->deleteJson('/api/v1/me/email-change')->assertNoContent();

    EmailChangeRequest::factory()->for($user)->create();

    fromFrontend()->deleteJson('/api/v1/me/email-change')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '3600');
});
