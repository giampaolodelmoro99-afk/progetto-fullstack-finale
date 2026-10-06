<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Orders\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderShippedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * The only place that changes orders.status. It rereads the order under
 * lock, checks the pair of states, fills the matching timestamp and writes
 * the history row with the author (null = system) and the optional reason.
 * The customer is notified only for the closed list of transitions (paid →
 * shipped, shipped → delivered, paid → cancelled), after the commit, and
 * never at the fictitious address of an anonymized account. Inside an outer
 * transaction it runs in a savepoint.
 */
final class ChangeOrderStatusAction
{
    public function execute(Order $order, OrderStatus $to, ?User $by = null, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidOrderTransitionException($from, $to);
            }

            $now = now();
            $locked->status = $to;

            if ($to->timestampColumn() !== null) {
                $locked->{$to->timestampColumn()} = $now;
            }

            $locked->save();

            $locked->statusChanges()->forceCreate([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $by?->id,
                'reason' => $reason,
                'created_at' => $now,
            ]);

            $this->notifyCustomer($locked, $from, $to);

            return $locked;
        });
    }

    private function notifyCustomer(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        $notification = self::notificationFor($order, $from, $to);
        $customer = $order->loadMissing('user')->user;

        if ($notification === null || $customer->isAnonymized()) {
            return;
        }

        $customer->notify($notification->afterCommit());
    }

    private static function notificationFor(Order $order, OrderStatus $from, OrderStatus $to): ?Notification
    {
        return match ([$from, $to]) {
            [OrderStatus::Paid, OrderStatus::Shipped] => new OrderShippedNotification($order),
            [OrderStatus::Shipped, OrderStatus::Delivered] => new OrderDeliveredNotification($order),
            [OrderStatus::Paid, OrderStatus::Cancelled] => new OrderCancelledNotification($order),
            default => null,
        };
    }
}
