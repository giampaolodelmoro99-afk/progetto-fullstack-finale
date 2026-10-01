<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\SecurityNotice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasswordChangedNotification extends Notification implements ShouldQueue
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
            ->subject('La tua password è stata cambiata')
            ->greeting("Ciao {$notifiable->name},")
            ->line('la password del tuo account è stata cambiata '.SecurityNotice::occurredOn($this->occurredAt, $this->device).'.')
            ->line('Per sicurezza abbiamo chiuso le altre sessioni aperte.')
            ->line('Se non hai fatto tu questa operazione, reimposta subito la password dalla pagina di accesso con «Password dimenticata» e controlla la pagina Sicurezza del tuo account.');
    }
}
