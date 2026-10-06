<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Transition shipped → delivered, sent after the commit.
 */
final class OrderDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

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
            ->subject("Il tuo ordine {$this->order->number} è stato consegnato")
            ->greeting("Ciao {$notifiable->name},")
            ->line("il tuo ordine {$this->order->number} è stato consegnato. Grazie per aver scelto Casa del Silicio.")
            ->line('Se qualcosa non va, hai 30 giorni per il reso: trovi i dettagli nella pagina Assistenza.')
            ->action(OrderMail::VIEW_ORDER, OrderMail::url($this->order));
    }
}
