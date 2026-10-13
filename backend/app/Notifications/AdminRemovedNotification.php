<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on demand to the original address after the removal of an admin is
 * committed: the account now has a fictitious .invalid address. A removal
 * the admin did not expect may mean someone got into the server, so the
 * notice points to the person in charge of the shop, not to the customer
 * support.
 */
final class AdminRemovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly CarbonInterface $occurredAt,
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
        $date = $this->occurredAt->locale('it')->translatedFormat('j F Y');

        return (new MailMessage)
            ->subject('Il tuo account di amministrazione è stato rimosso')
            ->greeting("Ciao {$this->name},")
            ->line("il tuo account di amministrazione è stato rimosso il {$date} alle {$this->occurredAt->format('H:i')}.")
            ->line('Se non te lo aspettavi, contatta subito il responsabile del negozio.');
    }
}
