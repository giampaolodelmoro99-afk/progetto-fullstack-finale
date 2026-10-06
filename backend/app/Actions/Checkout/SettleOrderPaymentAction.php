<?php

namespace App\Actions\Checkout;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\PaymentResult;
use Illuminate\Support\Facades\DB;

/**
 * Second transaction of the checkout: records the outcome of the charge on
 * a pending order. Used by PlaceOrderAction right after the gateway answers
 * and by orders:reconcile-pending for the checkouts interrupted between the
 * charge and this step. An order no longer pending was already settled by
 * the other path: nothing happens and the current order is returned.
 *
 * Success (lock order: user, order, cart rows): succeeded payment with the
 * last four digits, pending → paid, cart emptied; after the commit
 * OrderPlaced sends the confirmation.
 *
 * Failure (lock order: order, products by id, soft deleted ones included):
 * failed payment when the gateway saw a card, pending → cancelled by the
 * system with the reason of the gateway, stock given back for every row
 * that still has a product. The cart stays as it was.
 */
final class SettleOrderPaymentAction
{
    public function __construct(private readonly ChangeOrderStatusAction $changeStatus) {}

    public function execute(Order $order, PaymentResult $result): Order
    {
        if (! $result->succeeded) {
            return DB::transaction(fn (): Order => $this->recordFailure($order, $result));
        }

        $settled = DB::transaction(fn (): ?Order => $this->recordSuccess($order, $result));

        if ($settled === null) {
            return $order->refresh();
        }

        OrderPlaced::dispatch($settled);

        return $settled;
    }

    /**
     * Null when the order was no longer pending.
     */
    private function recordSuccess(Order $order, PaymentResult $result): ?Order
    {
        User::query()->lockForUpdate()->findOrFail($order->user_id);
        $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

        if ($locked->status !== OrderStatus::Pending) {
            return null;
        }

        $locked->payments()->create([
            'provider' => config('payment.driver'),
            'provider_reference' => $result->reference,
            'amount' => $locked->total,
            'status' => PaymentStatus::Succeeded,
            'card_last_four' => $result->cardLastFour,
        ]);

        $paid = $this->changeStatus->execute($locked, OrderStatus::Paid);

        CartItem::query()
            ->whereIn('cart_id', Cart::query()->where('user_id', $locked->user_id)->select('id'))
            ->delete();

        return $paid;
    }

    private function recordFailure(Order $order, PaymentResult $result): Order
    {
        $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

        if ($locked->status !== OrderStatus::Pending) {
            return $locked;
        }

        $items = $locked->items()->whereNotNull('product_id')->get();
        $products = Product::withTrashed()
            ->whereIn('id', $items->pluck('product_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($result->cardLastFour !== null) {
            $locked->payments()->create([
                'provider' => config('payment.driver'),
                'provider_reference' => $result->reference,
                'amount' => $locked->total,
                'status' => PaymentStatus::Failed,
                'card_last_four' => $result->cardLastFour,
            ]);
        }

        $cancelled = $this->changeStatus->execute($locked, OrderStatus::Cancelled, reason: $result->failureReason);

        foreach ($items as $item) {
            $products[$item->product_id]->increment('stock', $item->quantity);
        }

        return $cancelled;
    }
}
