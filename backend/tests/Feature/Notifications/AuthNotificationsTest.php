<?php

use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use App\Notifications\EmailChangeCancelledNotification;
use App\Notifications\EmailChangeConfirmationNotification;
use App\Notifications\EmailChangedNotification;
use App\Notifications\EmailChangeRequestedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\RecoveryCodesRegeneratedNotification;
use App\Notifications\RecoveryCodeUsedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TwoFactorDisabledNotification;
use App\Notifications\TwoFactorEnabledNotification;
use App\Notifications\VerifyEmailNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

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

it('writes the password change notice with date, time and device', function () {
    $user = User::factory()->make(['name' => 'Giulia Ferri']);

    $mail = (new PasswordChangedNotification(CarbonImmutable::parse('2026-10-01 16:52:00'), 'Chrome su macOS'))->toMail($user);

    expect($mail->subject)->toBe('La tua password è stata cambiata')
        ->and($mail->greeting)->toBe('Ciao Giulia Ferri,')
        ->and($mail->introLines[0])->toBe('la password del tuo account è stata cambiata il 1 ottobre 2026 alle 16:52 da Chrome su macOS.')
        ->and($mail->introLines[1])->toBe('Per sicurezza abbiamo chiuso le altre sessioni aperte.')
        ->and(end($mail->introLines))->toContain('reimposta subito la password');
});

it('writes the confirmation of the new address with a 60 minute link', function () {
    $mail = (new EmailChangeConfirmationNotification('Giulia Ferri', 'token-di-prova'))
        ->toMail((new AnonymousNotifiable)->route('mail', 'giulia.nuova@esempio.it'));

    expect($mail->subject)->toBe('Conferma il nuovo indirizzo email')
        ->and($mail->greeting)->toBe('Ciao Giulia Ferri,')
        ->and($mail->actionText)->toBe('Conferma il nuovo indirizzo')
        ->and($mail->actionUrl)->toBe('http://localhost:5173/confirm-email-change?token=token-di-prova')
        ->and(implode(' ', $mail->outroLines))->toContain('Il link è valido per 60 minuti.');
});

it('writes the notice of the requested change with the cancel link', function () {
    $user = User::factory()->make(['name' => 'Giulia Ferri']);

    $mail = (new EmailChangeRequestedNotification(CarbonImmutable::parse('2026-10-01 16:52:00'), 'Safari su iOS', 'giulia.nuova@esempio.it', 'token-di-annullamento'))->toMail($user);

    expect($mail->subject)->toBe('Richiesta di cambio email')
        ->and($mail->introLines[0])->toBe('è stato chiesto di cambiare l\'indirizzo email del tuo account in giulia.nuova@esempio.it il 1 ottobre 2026 alle 16:52 da Safari su iOS.')
        ->and($mail->actionText)->toBe('Annulla il cambio')
        ->and($mail->actionUrl)->toBe('http://localhost:5173/cancel-email-change?token=token-di-annullamento')
        ->and(implode(' ', $mail->outroLines))->toContain('Il link è valido per 60 minuti.')
        ->and(end($mail->outroLines))->toBe('Se non hai fatto tu questa operazione, cambia subito la password e controlla la pagina Sicurezza del tuo account.');
});

it('writes the notice of the change cancelled from the profile', function () {
    $user = User::factory()->make(['name' => 'Giulia Ferri']);

    $mail = (new EmailChangeCancelledNotification(CarbonImmutable::parse('2026-10-01 16:52:00'), 'Firefox su Windows'))->toMail($user);

    expect($mail->subject)->toBe('Cambio email annullato')
        ->and($mail->introLines[0])->toBe('la richiesta di cambiare l\'indirizzo email del tuo account è stata annullata il 1 ottobre 2026 alle 16:52 da Firefox su Windows.')
        ->and(end($mail->introLines))->toBe('Se non hai fatto tu questa operazione, cambia subito la password e controlla la pagina Sicurezza del tuo account.');
});

it('writes the notice of the changed address for the old one', function () {
    $mail = (new EmailChangedNotification('Giulia Ferri', 'giulia.nuova@esempio.it', CarbonImmutable::parse('2026-10-01 16:52:00'), 'Chrome su Android'))
        ->toMail((new AnonymousNotifiable)->route('mail', 'giulia.ferri@esempio.it'));

    expect($mail->subject)->toBe('Il tuo indirizzo email è stato cambiato')
        ->and($mail->greeting)->toBe('Ciao Giulia Ferri,')
        ->and($mail->introLines[0])->toBe('l\'indirizzo email del tuo account è stato cambiato in giulia.nuova@esempio.it il 1 ottobre 2026 alle 16:52 da Chrome su Android.')
        ->and(end($mail->introLines))->toContain('contatta subito l\'assistenza');
});

it('writes the notice of the deleted account with the retained orders and the support contacts', function () {
    $mail = (new AccountDeletedNotification('Giulia Ferri', CarbonImmutable::parse('2026-10-01 16:52:00'), [
        'Scrivici ad assistenza@casadelsilicio.example o chiamaci al +39 050 000 0000.',
        'Rispondiamo dal lunedì al venerdì, dalle 9:00 alle 18:00.',
    ]))->toMail((new AnonymousNotifiable)->route('mail', 'giulia.ferri@esempio.it'));

    expect($mail->subject)->toBe('Il tuo account è stato eliminato')
        ->and($mail->greeting)->toBe('Ciao Giulia Ferri,')
        ->and($mail->introLines)->toBe([
            'il tuo account è stato eliminato il 1 ottobre 2026 alle 16:52.',
            'Abbiamo cancellato i tuoi dati personali: indirizzi, carrello, sessioni e attività di sicurezza.',
            'I tuoi ordini restano conservati, senza il tuo nome e il tuo indirizzo email, per gli obblighi fiscali.',
            'Se non hai fatto tu questa operazione, contatta l\'assistenza:',
            'Scrivici ad assistenza@casadelsilicio.example o chiamaci al +39 050 000 0000.',
            'Rispondiamo dal lunedì al venerdì, dalle 9:00 alle 18:00.',
        ])
        ->and(implode(' ', $mail->introLines))->not->toContain('cambia subito la password');
});

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
    PasswordChangedNotification::class,
    EmailChangeConfirmationNotification::class,
    EmailChangeRequestedNotification::class,
    EmailChangeCancelledNotification::class,
    EmailChangedNotification::class,
    AccountDeletedNotification::class,
]);

it('translates the texts of the email layout', function () {
    expect(__('Regards,'))->toBe('A presto,')
        ->and(__('All rights reserved.'))->toBe('Tutti i diritti riservati.');
});
