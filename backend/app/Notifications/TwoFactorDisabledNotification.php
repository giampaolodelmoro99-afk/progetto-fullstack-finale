<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\SecurityNotice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TwoFactorDisabledNotification extends Notification implements ShouldQueue
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
            ->subject('Doppia autenticazione disattivata')
            ->greeting("Ciao {$notifiable->name},")
            ->line('la doppia autenticazione del tuo account è stata disattivata '.SecurityNotice::occurredOn($this->occurredAt, $this->device).'.')
            ->line('I codici di recupero non sono più validi e abbiamo chiuso le altre sessioni aperte.')
            ->line(SecurityNotice::NOT_YOU);
    }
}
