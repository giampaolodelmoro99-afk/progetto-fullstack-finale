<?php

namespace App\Actions\Orders;

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Orders\InvalidOrderTransitionException;
use App\Exceptions\Orders\RefundFailedException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only way to cancel a paid order, for the customer and for the admin.
 *
 * 1. The order must be paid with a succeeded payment, otherwise 422 before
 *    any effect and without calling the gateway.
 * 2. Refund outside any transaction, with the payment id as idempotency key.
 * 3. A failed refund changes nothing: RefundFailedException (502).
 * 4. Transaction, lock order: order → payment → products by id (soft deleted
 *    ones included). Under the locks the order must still be paid and the
 *    payment still succeeded: a concurrent request may have settled it, and
 *    then the current order is returned without effects. Otherwise stock
 *    given back, payment refunded and paid → cancelled with the author.
 *
 * Accepted limits: two concurrent cancellations both call the gateway
 * before the lock (the idempotency key prevents a double refund, the check
 * under lock a double transition); an order shipped by the admin between
 * the refund and the lock stays shipped with its payment refunded: a
 * warning in the log lets the admin sort it out.
 */
final class CancelOrderAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ChangeOrderStatusAction $changeStatus,
    ) {}

    /**
     * @throws InvalidOrderTransitionException
     * @throws RefundFailedException
     */
    public function execute(Order $order, User $by): Order
    {
        $payment = $order->payments()->where('status', PaymentStatus::Succeeded)->latest('id')->first();

        if ($order->status !== OrderStatus::Paid || $payment === null) {
            throw new InvalidOrderTransitionException($order->status, OrderStatus::Cancelled);
        }

        $refund = $this->gateway->refund($payment);

        if (! $refund->succeeded) {
            throw new RefundFailedException($order->id);
        }

        return DB::transaction(function () use ($order, $payment, $by): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== OrderStatus::Paid || $lockedPayment->status !== PaymentStatus::Succeeded) {
                if ($locked->status !== OrderStatus::Cancelled) {
                    Log::warning('Refunded payment of an order that was not cancelled.', [
                        'order_id' => $locked->id,
                        'payment_id' => $lockedPayment->id,
                        'status' => $locked->status->value,
                    ]);
                }

                return $locked;
            }

            $items = $locked->items()->whereNotNull('product_id')->get();
            $products = Product::withTrashed()
                ->whereIn('id', $items->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $products[$item->product_id]->increment('stock', $item->quantity);
            }

            $lockedPayment->update(['status' => PaymentStatus::Refunded]);

            return $this->changeStatus->execute($locked, OrderStatus::Cancelled, $by);
        });
    }
}
