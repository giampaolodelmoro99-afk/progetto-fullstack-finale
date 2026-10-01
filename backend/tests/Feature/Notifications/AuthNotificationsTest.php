<?php

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

it('writes the verification email in italian with a 24 hour link', function () {
    $user = User::factory()->unverified()->make(['id' => 7, 'name' => 'Paolo Greco']);

    $mail = (new VerifyEmailNotification)->toMail($user);

    expect($mail->subject)->toBe('Conferma il tuo indirizzo email')
        ->and($mail->greeting)->toBe('Ciao Paolo Greco,')
        ->and($mail->actionText)->toBe('Conferma l\'indirizzo')
        ->and($mail->actionUrl)->toStartWith('http://localhost:5173/verify-email?id=7&hash=')
        ->and(implode(' ', $mail->outroLines))->toContain('Il link è valido per 24 ore.');
});

it('writes the reset email in italian with a 60 minute link', function () {
    $user = User::factory()->make(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it']);

    $mail = (new ResetPasswordNotification('token-di-prova'))->toMail($user);

    expect($mail->subject)->toBe('Reimposta la password')
        ->and($mail->greeting)->toBe('Ciao Giulia Ferri,')
        ->and($mail->actionText)->toBe('Scegli una nuova password')
        ->and($mail->actionUrl)->toBe('http://localhost:5173/reset-password?token=token-di-prova&email=giulia.ferri%40esempio.it')
        ->and(implode(' ', $mail->outroLines))->toContain('Il link è valido per 60 minuti.');
});

it('writes the password reset notice with date, time and device', function () {
    $user = User::factory()->make(['name' => 'Giulia Ferri']);

    $mail = (new PasswordResetNotification(CarbonImmutable::parse('2026-10-01 16:52:00'), 'Chrome su macOS'))->toMail($user);

    expect($mail->subject)->toBe('La tua password è stata reimpostata')
        ->and($mail->introLines[0])->toBe('la password del tuo account è stata reimpostata il 1 ottobre 2026 alle 16:52 da Chrome su macOS.')
        ->and(implode(' ', $mail->introLines))->toContain('reimposta subito la password');
});

it('queues every authentication email', function (string $notification) {
    expect(is_subclass_of($notification, ShouldQueue::class))->toBeTrue();
})->with([
    VerifyEmailNotification::class,
    ResetPasswordNotification::class,
    PasswordResetNotification::class,
]);

it('translates the texts of the email layout', function () {
    expect(__('Regards,'))->toBe('A presto,')
        ->and(__('All rights reserved.'))->toBe('Tutti i diritti riservati.');
});
