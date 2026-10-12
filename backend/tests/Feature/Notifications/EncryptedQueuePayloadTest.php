<?php

use App\Models\User;
use App\Notifications\EmailChangeConfirmationNotification;
use App\Notifications\EmailChangeRequestedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['queue.default' => 'database']);
});

function queuedCommand(): string
{
    $payload = json_decode(DB::table('jobs')->sole()->payload, true);

    return $payload['data']['command'];
}

function decryptedCommand(): object
{
    return unserialize(Crypt::decrypt(queuedCommand()));
}

it('encrypts the reset token in the queued payload', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    $user->notify(new ResetPasswordNotification('token-di-reset-in-coda'));

    expect(queuedCommand())
        ->not->toStartWith('O:')
        ->not->toContain('token-di-reset-in-coda')
        ->and(decryptedCommand())->toBeInstanceOf(SendQueuedNotifications::class)
        ->and(decryptedCommand()->notification->token)->toBe('token-di-reset-in-coda');
});

it('encrypts the confirmation token of the new address in the queued payload', function () {
    Notification::route('mail', 'giulia.nuova@esempio.it')
        ->notify(new EmailChangeConfirmationNotification('Giulia Ferri', 'token-di-conferma-in-coda'));

    expect(queuedCommand())
        ->not->toStartWith('O:')
        ->not->toContain('token-di-conferma-in-coda')
        ->not->toContain('giulia.nuova@esempio.it')
        ->and(decryptedCommand()->notification->token)->toBe('token-di-conferma-in-coda');
});

it('encrypts the cancel token of the requested change in the queued payload', function () {
    $user = User::factory()->create(['email' => 'giulia.ferri@esempio.it']);

    $user->notify(new EmailChangeRequestedNotification(
        CarbonImmutable::parse('2026-10-01 16:52:00'),
        'Safari su iOS',
        'giulia.nuova@esempio.it',
        'token-di-annullamento-in-coda',
    ));

    expect(queuedCommand())
        ->not->toStartWith('O:')
        ->not->toContain('token-di-annullamento-in-coda')
        ->not->toContain('giulia.nuova@esempio.it')
        ->and(decryptedCommand()->notification->cancelToken)->toBe('token-di-annullamento-in-coda');
});

it('encrypts the verification notification in the queued payload', function () {
    $user = User::factory()->unverified()->create(['email' => 'giulia.ferri@esempio.it']);

    $user->notify(new VerifyEmailNotification);

    expect(queuedCommand())
        ->not->toStartWith('O:')
        ->not->toContain('VerifyEmailNotification')
        ->and(decryptedCommand()->notification)->toBeInstanceOf(VerifyEmailNotification::class);
});

it('still serializes the other notifications in clear', function () {
    $user = User::factory()->create();

    $user->notify(new PasswordChangedNotification(CarbonImmutable::parse('2026-10-01 16:52:00'), 'Safari su iOS'));

    expect(queuedCommand())->toStartWith('O:');
});
