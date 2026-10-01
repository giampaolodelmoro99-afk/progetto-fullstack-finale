<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('answers the same way for a registered and an unknown address', function (string $email) {
    User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/forgot-password', ['email' => $email])
        ->assertOk()
        ->assertExactJson([
            'message' => 'Se l\'indirizzo è registrato, riceverai un\'email con il link per reimpostare la password.',
        ]);
})->with([
    'registered' => 'giulia.ferri@esempio.it',
    'unknown' => 'nessuno@esempio.it',
]);

it('sends the link to the frontend page only to a registered address', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    fromFrontend()->postJson('/api/v1/forgot-password', ['email' => 'Giulia.Ferri@esempio.it'])->assertOk();
    fromFrontend()->postJson('/api/v1/forgot-password', ['email' => 'nessuno@esempio.it'])->assertOk();

    Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
        expect($notification->toMail($user)->actionUrl)
            ->toBe("http://localhost:5173/reset-password?token={$notification->token}&email=giulia.ferri%40esempio.it");

        return true;
    });
    expect(DB::table('password_reset_tokens')->where('email', 'giulia.ferri@esempio.it')->count())->toBe(1);
});

it('validates the email', function (array $data) {
    fromFrontend()->postJson('/api/v1/forgot-password', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
})->with([
    'missing' => [[]],
    'malformed' => [['email' => 'giulia']],
]);

it('limits the requests to 3 every 10 minutes for each email and ip', function () {
    $this->freezeTime();

    foreach (range(1, 3) as $attempt) {
        fromFrontend()->postJson('/api/v1/forgot-password', ['email' => 'giulia.ferri@esempio.it'])->assertOk();
    }

    fromFrontend()->postJson('/api/v1/forgot-password', ['email' => 'giulia.ferri@esempio.it'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '600');
});
