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
 * Transition paid → shipped, sent after the commit.
 */
final class OrderShippedNotification extends Notification implements ShouldQueue
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
            ->subject("Il tuo ordine {$this->order->number} è stato spedito")
            ->greeting("Ciao {$notifiable->name},")
            ->line("il tuo ordine {$this->order->number} è partito: la consegna è prevista in 24/48 ore.")
            ->line('Spedizione a: '.OrderMail::address($this->order))
            ->action(OrderMail::VIEW_ORDER, OrderMail::url($this->order));
    }
}
