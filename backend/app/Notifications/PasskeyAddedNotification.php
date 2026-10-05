<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\SecurityNotice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasskeyAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $passkeyName,
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
            ->subject('Passkey aggiunta')
            ->greeting("Ciao {$notifiable->name},")
            ->line("la passkey «{$this->passkeyName}» è stata aggiunta al tuo account ".SecurityNotice::occurredOn($this->occurredAt, $this->device).'.')
            ->line('Da ora puoi usarla per accedere senza password.')
            ->line(SecurityNotice::NOT_YOU);
    }
}
