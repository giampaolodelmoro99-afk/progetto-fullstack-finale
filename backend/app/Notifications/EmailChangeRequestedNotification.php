<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\SecurityNotice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notice to the current address when a change is requested, with the link
 * that cancels it without logging in (/cancel-email-change).
 */
final class EmailChangeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CarbonInterface $occurredAt,
        public readonly string $device,
        public readonly string $newEmail,
        #[\SensitiveParameter] public readonly string $cancelToken,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Richiesta di cambio email')
            ->greeting("Ciao {$notifiable->name},")
            ->line("è stato chiesto di cambiare l'indirizzo email del tuo account in {$this->newEmail} ".SecurityNotice::occurredOn($this->occurredAt, $this->device).'.')
            ->line('Finché il nuovo indirizzo non viene confermato, il tuo account continua a usare questo.')
            ->action('Annulla il cambio', config('app.frontend_url').'/cancel-email-change?'.http_build_query(['token' => $this->cancelToken]))
            ->line('Il link è valido per 60 minuti.')
            ->line(SecurityNotice::NOT_YOU);
    }
}
