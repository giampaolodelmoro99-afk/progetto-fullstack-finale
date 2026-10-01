<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notice sent after a password reset. Date and device are captured when the
 * reset happens, because a queued notification has no request.
 */
final class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CarbonInterface $resetAt,
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
        $date = $this->resetAt->locale('it')->translatedFormat('j F Y');
        $time = $this->resetAt->format('H:i');

        return (new MailMessage)
            ->subject('La tua password è stata reimpostata')
            ->greeting("Ciao {$notifiable->name},")
            ->line("la password del tuo account è stata reimpostata il {$date} alle {$time} da {$this->device}.")
            ->line('Per sicurezza abbiamo chiuso tutte le sessioni aperte: accedi di nuovo con la nuova password.')
            ->line('Se non hai fatto tu questa operazione, reimposta subito la password dalla pagina di accesso con «Password dimenticata».');
    }
}
