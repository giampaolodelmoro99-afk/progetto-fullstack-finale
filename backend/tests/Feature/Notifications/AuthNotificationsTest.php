<?php

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Notifications\RecoveryCodesRegeneratedNotification;
use App\Notifications\RecoveryCodeUsedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TwoFactorDisabledNotification;
use App\Notifications\TwoFactorEnabledNotification;
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

it('writes the two-factor notices with date, time, device and the invitation to change the password', function (string $notification, string $subject, string $firstLine) {
    $user = User::factory()->make(['name' => 'Davide Conti']);

    $mail = (new $notification(CarbonImmutable::parse('2026-10-01 16:52:00'), 'Safari su iOS'))->toMail($user);

    expect($mail->subject)->toBe($subject)
        ->and($mail->greeting)->toBe('Ciao Davide Conti,')
        ->and($mail->introLines[0])->toBe($firstLine)
        ->and(end($mail->introLines))->toBe('Se non hai fatto tu questa operazione, cambia subito la password e controlla la pagina Sicurezza del tuo account.');
})->with([
    'enabled' => [TwoFactorEnabledNotification::class, 'Doppia autenticazione attivata', 'la doppia autenticazione del tuo account è stata attivata il 1 ottobre 2026 alle 16:52 da Safari su iOS.'],
    'disabled' => [TwoFactorDisabledNotification::class, 'Doppia autenticazione disattivata', 'la doppia autenticazione del tuo account è stata disattivata il 1 ottobre 2026 alle 16:52 da Safari su iOS.'],
    'regenerated' => [RecoveryCodesRegeneratedNotification::class, 'Codici di recupero rigenerati', 'i codici di recupero del tuo account sono stati rigenerati il 1 ottobre 2026 alle 16:52 da Safari su iOS.'],
]);

it('writes the recovery code notice with the codes left', function (int $remaining, string $left, bool $suggestsNewCodes) {
    $user = User::factory()->make(['name' => 'Davide Conti']);

    $mail = (new RecoveryCodeUsedNotification(CarbonImmutable::parse('2026-10-01 16:52:00'), 'Chrome su Android', $remaining))->toMail($user);
    $text = implode(' ', $mail->introLines);

    expect($mail->subject)->toBe('Codice di recupero usato')
        ->and($mail->introLines[0])->toBe('un codice di recupero del tuo account è stato usato il 1 ottobre 2026 alle 16:52 da Chrome su Android.')
        ->and($mail->introLines[1])->toBe($left)
        ->and(str_contains($text, 'Ti consigliamo di generarne di nuovi'))->toBe($suggestsNewCodes)
        ->and($text)->toContain('cambia subito la password');
})->with([
    '7 left' => [7, 'Ti restano 7 codici di recupero.', false],
    '3 left' => [3, 'Ti restano 3 codici di recupero.', false],
    '2 left' => [2, 'Ti restano 2 codici di recupero.', true],
    '1 left' => [1, 'Ti resta 1 codice di recupero.', true],
    'none left' => [0, 'Non ti restano codici di recupero.', true],
]);

it('queues every authentication email', function (string $notification) {
    expect(is_subclass_of($notification, ShouldQueue::class))->toBeTrue();
})->with([
    VerifyEmailNotification::class,
    ResetPasswordNotification::class,
    PasswordResetNotification::class,
    TwoFactorEnabledNotification::class,
    TwoFactorDisabledNotification::class,
    RecoveryCodesRegeneratedNotification::class,
    RecoveryCodeUsedNotification::class,
]);

it('translates the texts of the email layout', function () {
    expect(__('Regards,'))->toBe('A presto,')
        ->and(__('All rights reserved.'))->toBe('Tutti i diritti riservati.');
});
