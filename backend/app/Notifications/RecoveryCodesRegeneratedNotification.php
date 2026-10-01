<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\SecurityNotice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RecoveryCodesRegeneratedNotification extends Notification implements ShouldQueue
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
            ->subject('Codici di recupero rigenerati')
            ->greeting("Ciao {$notifiable->name},")
            ->line('i codici di recupero del tuo account sono stati rigenerati '.SecurityNotice::occurredOn($this->occurredAt, $this->device).'.')
            ->line('I codici precedenti non sono più validi.')
            ->line(SecurityNotice::NOT_YOU);
    }
}
