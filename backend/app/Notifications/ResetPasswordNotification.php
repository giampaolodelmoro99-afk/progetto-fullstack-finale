<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The link opens the frontend page /reset-password with token and email
 * (ResetPassword::createUrlUsing in AppServiceProvider).
 * The link performs an action, so the queued payload is encrypted with
 * APP_KEY: with an asynchronous driver it never sits in clear in jobs.
 */
final class ResetPasswordNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reimposta la password')
            ->greeting("Ciao {$notifiable->name},")
            ->line('abbiamo ricevuto una richiesta per reimpostare la password del tuo account.')
            ->action('Scegli una nuova password', $this->resetUrl($notifiable))
            ->line('Il link è valido per 60 minuti.')
            ->line('Se non hai chiesto tu di reimpostare la password, ignora questa email: la password attuale resta valida.');
    }
}
