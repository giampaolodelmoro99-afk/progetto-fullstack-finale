<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The link opens the frontend page /verify-email with the signed parameters
 * of the API route (VerifyEmail::createUrlUsing in AppServiceProvider).
 * The link performs an action, so the queued payload is encrypted with
 * APP_KEY: with an asynchronous driver it never sits in clear in jobs.
 */
final class VerifyEmailNotification extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Conferma il tuo indirizzo email')
            ->greeting("Ciao {$notifiable->name},")
            ->line('grazie per la registrazione. Conferma il tuo indirizzo email per completare l\'account.')
            ->action('Conferma l\'indirizzo', $this->verificationUrl($notifiable))
            ->line('Il link è valido per 24 ore.')
            ->line('Se non hai creato tu l\'account, ignora questa email.');
    }
}
