<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on demand to the new address, which belongs to no user yet. The
 * link opens the frontend page /confirm-email-change with the plain token,
 * which exists only here: the database keeps its SHA-256 hash.
 */
final class EmailChangeConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        #[\SensitiveParameter] public readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Conferma il nuovo indirizzo email')
            ->greeting("Ciao {$this->name},")
            ->line('hai chiesto di usare questo indirizzo per il tuo account. Conferma che è tuo per completare il cambio.')
            ->action('Conferma il nuovo indirizzo', config('app.frontend_url').'/confirm-email-change?'.http_build_query(['token' => $this->token]))
            ->line('Il link è valido per 60 minuti.')
            ->line('Se non hai chiesto tu questo cambio, ignora questa email: l\'indirizzo del tuo account non cambierà.');
    }
}
