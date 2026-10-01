<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on demand to the original address after the deletion is committed:
 * the account now has a fictitious .invalid address. There is no account
 * left whose password could be changed, so the notice points to the
 * support contacts (section contatti of the support page).
 */
final class AccountDeletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $contacts
     */
    public function __construct(
        public readonly string $name,
        public readonly CarbonInterface $occurredAt,
        public readonly array $contacts,
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

        $mail = (new MailMessage)
            ->subject('Il tuo account è stato eliminato')
            ->greeting("Ciao {$this->name},")
            ->line("il tuo account è stato eliminato il {$date} alle {$this->occurredAt->format('H:i')}.")
            ->line('Abbiamo cancellato i tuoi dati personali: indirizzi, carrello, sessioni e attività di sicurezza.')
            ->line('I tuoi ordini restano conservati, senza il tuo nome e il tuo indirizzo email, per gli obblighi fiscali.')
            ->line('Se non hai fatto tu questa operazione, contatta l\'assistenza:');

        foreach ($this->contacts as $contact) {
            $mail->line($contact);
        }

        return $mail;
    }
}
