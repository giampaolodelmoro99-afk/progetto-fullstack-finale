<?php

use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('sends a new verification link', function () {
    $user = actingAsCustomer();

    fromFrontend()->postJson('/api/v1/email/verification-notification')
        ->assertAccepted()
        ->assertExactJson(['message' => 'Ti abbiamo inviato un nuovo link di conferma.']);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('sends nothing when the email is already verified', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->postJson('/api/v1/email/verification-notification')
        ->assertOk()
        ->assertExactJson(['message' => 'Il tuo indirizzo email è già confermato.']);

    Notification::assertNothingSent();
});

it('rejects a guest', function () {
    fromFrontend()->postJson('/api/v1/email/verification-notification')->assertUnauthorized();
});

it('limits the user to 6 requests per minute', function () {
    $this->freezeTime();

    actingAsCustomer();

    foreach (range(1, 6) as $attempt) {
        fromFrontend()->postJson('/api/v1/email/verification-notification')->assertAccepted();
    }

    fromFrontend()->postJson('/api/v1/email/verification-notification')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
});
