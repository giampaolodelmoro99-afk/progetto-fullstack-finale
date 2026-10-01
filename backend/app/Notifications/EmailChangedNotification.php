<?php

namespace App\Notifications;

use App\Support\SecurityNotice;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on demand to the previous address once the change is confirmed: the
 * account no longer uses it, so the user model would send it to the new one.
 * The confirmed change cannot be undone from a link, so the notice points
 * to the password reset and to the support.
 */
final class EmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $newEmail,
        public readonly CarbonInterface $occurredAt,
        public readonly string $device,
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
            ->subject('Il tuo indirizzo email è stato cambiato')
            ->greeting("Ciao {$this->name},")
            ->line("l'indirizzo email del tuo account è stato cambiato in {$this->newEmail} ".SecurityNotice::occurredOn($this->occurredAt, $this->device).'.')
            ->line('Da ora le comunicazioni del negozio arriveranno al nuovo indirizzo.')
            ->line('Se non hai fatto tu questa operazione, contatta subito l\'assistenza dalla pagina Assistenza del negozio.');
    }
}
