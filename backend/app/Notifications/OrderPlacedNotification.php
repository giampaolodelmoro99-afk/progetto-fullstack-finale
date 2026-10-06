<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use App\Support\OrderMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation of a paid order, the only email of a successful payment:
 * sent by the OrderPlaced listener after the commit. Names, prices and
 * address come from the snapshot of the order.
 */
final class OrderPlacedNotification extends Notification implements ShouldQueue
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
        $order = $this->order->loadMissing('items');

        $mail = (new MailMessage)
            ->subject("Conferma dell'ordine {$order->number}")
            ->greeting("Ciao {$notifiable->name},")
            ->line("grazie per il tuo ordine {$order->number}: il pagamento è andato a buon fine.");

        foreach ($order->items as $item) {
            $mail->line(self::itemLine($item));
        }

        return $mail
            ->line('Subtotale: '.Money::format($order->subtotal))
            ->line('Spedizione: '.($order->shipping_cost === 0 ? 'Gratuita' : Money::format($order->shipping_cost)))
            ->line('Totale: '.Money::format($order->total).' (IVA inclusa)')
            ->line('Spedizione a: '.OrderMail::address($order))
            ->action(OrderMail::VIEW_ORDER, OrderMail::url($order))
            ->line('Ti scriveremo di nuovo quando l\'ordine sarà spedito.');
    }

    /**
     * Cuffie Aria × 1: 349,00 €
     */
    private static function itemLine(OrderItem $item): string
    {
        return "{$item->product_name} × {$item->quantity}: ".Money::format($item->line_total);
    }
}
