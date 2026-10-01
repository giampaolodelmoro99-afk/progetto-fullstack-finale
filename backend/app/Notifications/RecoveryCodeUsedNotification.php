<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Auth\SecondFactorResult;
use App\Support\SecurityNotice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RecoveryCodeUsedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CarbonInterface $occurredAt,
        public readonly string $device,
        public readonly int $remaining,
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
        $mail = (new MailMessage)
            ->subject('Codice di recupero usato')
            ->greeting("Ciao {$notifiable->name},")
            ->line('un codice di recupero del tuo account è stato usato '.SecurityNotice::occurredOn($this->occurredAt, $this->device).'.')
            ->line(match ($this->remaining) {
                0 => 'Non ti restano codici di recupero.',
                1 => 'Ti resta 1 codice di recupero.',
                default => "Ti restano {$this->remaining} codici di recupero.",
            });

        if ($this->remaining <= SecondFactorResult::LOW_RECOVERY_CODES) {
            $mail->line('Ti consigliamo di generarne di nuovi dalla pagina Sicurezza del tuo account.');
        }

        return $mail->line(SecurityNotice::NOT_YOU);
    }
}
