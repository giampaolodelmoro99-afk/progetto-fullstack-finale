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
 * Notice to the current address when the pending change is cancelled from
 * the profile.
 */
final class EmailChangeCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CarbonInterface $occurredAt,
        public readonly string $device,
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
            ->subject('Cambio email annullato')
            ->greeting("Ciao {$notifiable->name},")
            ->line('la richiesta di cambiare l\'indirizzo email del tuo account è stata annullata '.SecurityNotice::occurredOn($this->occurredAt, $this->device).'.')
            ->line('Il tuo account continua a usare questo indirizzo.')
            ->line(SecurityNotice::NOT_YOU);
    }
}
