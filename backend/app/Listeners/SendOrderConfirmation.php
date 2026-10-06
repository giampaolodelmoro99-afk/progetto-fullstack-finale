<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sends the order confirmation. An account anonymized in the meantime (a
 * checkout completed late by the reconciliation) has a fictitious .invalid
 * address: no email.
 */
final class SendOrderConfirmation implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $user = $event->order->loadMissing('user')->user;

        if ($user->isAnonymized()) {
            return;
        }

        $user->notify(new OrderPlacedNotification($event->order));
    }
}
