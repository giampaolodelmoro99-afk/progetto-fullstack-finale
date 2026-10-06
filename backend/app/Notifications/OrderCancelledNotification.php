<?php

namespace App\Notifications;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use App\Support\OrderMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Transition paid → cancelled with the refund, by the customer or by the
 * admin, sent after the commit.
 */
final class OrderCancelledNotification extends Notification implements ShouldQueue
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
        $payment = $this->order->loadMissing('payments')->payments
            ->firstWhere('status', PaymentStatus::Refunded);

        $mail = (new MailMessage)
            ->subject("Ordine {$this->order->number} annullato")
            ->greeting("Ciao {$notifiable->name},")
            ->line("il tuo ordine {$this->order->number} è stato annullato e non verrà spedito.");

        if ($payment !== null) {
            $mail->line('Ti abbiamo rimborsato '.Money::format($payment->amount)." sulla carta che termina con {$payment->card_last_four}: i tempi di accredito dipendono dalla tua banca.");
        }

        return $mail->action(OrderMail::VIEW_ORDER, OrderMail::url($this->order));
    }
}
