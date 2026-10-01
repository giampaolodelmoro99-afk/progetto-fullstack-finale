<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user, ?string $hash = null, int $minutes = 1440): string
{
    return URL::temporarySignedRoute('v1.verification.verify', now()->addMinutes($minutes), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ], absolute: false);
}

it('verifies the email of a guest without starting a session', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = User::factory()->unverified()->create(['email' => 'paolo.greco@esempio.it']);

    fromFrontend()->postJson(verificationUrl($user))
        ->assertOk()
        ->assertExactJson(['data' => ['email' => 'paolo.greco@esempio.it']]);

    expect($user->fresh()->email_verified_at->toDateTimeString())->toBe('2026-10-01 10:30:00');
    $this->assertGuest('web');

    $event = SecurityEvent::sole();
    expect($event->user_id)->toBe($user->id)
        ->and($event->type)->toBe(SecurityEventType::EmailVerified);
});

it('verifies the email of an authenticated user', function () {
    $user = actingAsCustomer();

    fromFrontend()->postJson(verificationUrl($user))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('shows the confirmed address even when another user is logged in', function () {
    actingAsVerifiedCustomer();
    $other = User::factory()->unverified()->create(['email' => 'paolo.greco@esempio.it']);

    fromFrontend()->postJson(verificationUrl($other))
        ->assertOk()
        ->assertJsonPath('data.email', 'paolo.greco@esempio.it');
});

it('answers 200 for an email that is already verified, without a new event', function () {
    $user = User::factory()->create(['email_verified_at' => '2026-09-01 09:00:00']);

    fromFrontend()->postJson(verificationUrl($user))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    expect($user->fresh()->email_verified_at->toDateTimeString())->toBe('2026-09-01 09:00:00')
        ->and(SecurityEvent::count())->toBe(0);
});

it('leaves the email unverified when the security event cannot be written', function () {
    $user = User::factory()->unverified()->create();
    SecurityEvent::creating(fn () => throw new RuntimeException('Scrittura non riuscita'));

    $this->withoutExceptionHandling();

    expect(fn () => fromFrontend()->postJson(verificationUrl($user)))
        ->toThrow(RuntimeException::class, 'Scrittura non riuscita');

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('rejects invalid links with the same message', function (string $case) {
    $user = User::factory()->unverified()->create(['email' => 'paolo.greco@esempio.it']);

    $url = match ($case) {
        'altered signature' => verificationUrl($user).'0',
        'hash of another address' => verificationUrl($user, sha1('vecchio@esempio.it')),
        'missing user' => URL::temporarySignedRoute('v1.verification.verify', now()->addDay(), ['id' => $user->id + 100, 'hash' => sha1('paolo.greco@esempio.it')], absolute: false),
        'no signature' => "/api/v1/email/verify/{$user->id}/".sha1('paolo.greco@esempio.it'),
    };

    fromFrontend()->postJson($url)
        ->assertForbidden()
        ->assertExactJson(['message' => 'Il link di verifica non è valido o è scaduto.']);

    expect($user->fresh()->email_verified_at)->toBeNull()
        ->and(SecurityEvent::count())->toBe(0);
})->with(['altered signature', 'hash of another address', 'missing user', 'no signature']);

it('rejects the link after a change of the email address', function () {
    $user = User::factory()->unverified()->create(['email' => 'paolo.greco@esempio.it']);
    $url = verificationUrl($user);
    $user->forceFill(['email' => 'paolo.nuovo@esempio.it'])->save();

    fromFrontend()->postJson($url)->assertForbidden();
});

it('accepts the link after 23 hours and rejects it after 25', function (int $hours, int $status) {
    $this->travelTo('2026-10-01 10:30:00');
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $url = '';
    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user, &$url) {
        $url = $notification->toMail($user)->actionUrl;

        return true;
    });
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    $this->travelTo(now()->addHours($hours));

    fromFrontend()->postJson("/api/v1/email/verify/{$query['id']}/{$query['hash']}?expires={$query['expires']}&signature={$query['signature']}")
        ->assertStatus($status);
})->with([
    'after 23 hours' => [23, 200],
    'after 25 hours' => [25, 403],
]);

it('sends a link to the frontend page with the signed parameters, valid for 24 hours', function () {
    $this->travelTo('2026-10-01 10:30:00');
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'paolo.greco@esempio.it']);

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        expect($url)->toStartWith('http://localhost:5173/verify-email?')
            ->and($query['id'])->toBe((string) $user->id)
            ->and($query['hash'])->toBe(sha1('paolo.greco@esempio.it'))
            ->and($query['expires'])->toBe('1790929800')
            ->and($query['signature'])->toMatch('/^[0-9a-f]{64}$/');

        return true;
    });
});

it('accepts the link whatever host generated it', function () {
    $user = User::factory()->unverified()->create();
    $path = verificationUrl($user);

    fromFrontend()->postJson('http://127.0.0.1:8000'.$path)->assertOk();
});

it('limits a guest to 6 attempts per minute', function () {
    $this->freezeTime();

    $user = User::factory()->unverified()->create();

    foreach (range(1, 6) as $attempt) {
        fromFrontend()->postJson(verificationUrl($user))->assertOk();
    }

    fromFrontend()->postJson(verificationUrl($user))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
});
