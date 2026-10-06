<?php

namespace App\Actions\Checkout;

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Exceptions\Checkout\EmptyCartException;
use App\Exceptions\Checkout\InsufficientStockException;
use App\Exceptions\Checkout\PaymentFailedException;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Auth\ActiveUserLock;
use App\Support\CartSummary;
use App\Support\PaymentResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns the cart into an order and charges it, in three steps.
 *
 * 1. Transaction, lock order user → cart and its rows → products by id
 *    (soft deleted ones included): an anonymized account is refused (401);
 *    an address deleted after the validation fails as an invalid field;
 *    the cart must not be empty and every row must be purchasable within
 *    the stock, checked once, under the locks; the pending order is created
 *    with the totals of CartSummary (the class of the cart), the snapshot
 *    of rows and address, its number and the creation row of the history,
 *    and the stock is decremented.
 * 2. The charge, outside any transaction: an external call.
 * 3. SettleOrderPaymentAction records the outcome in a second transaction.
 *
 * If the gateway throws, the exception is reported, the order is cancelled
 * at once and the stock given back (no payment row: the gateway may never
 * have seen the card), then the exception goes on: with the simulated
 * gateway nothing can have been charged. With a real gateway the error is ambiguous (the charge
 * may have happened), and the order should stay pending for
 * orders:reconcile-pending or the notifications of the provider.
 *
 * The card data reach only the gateway: never stored, logged or kept in an
 * exception (SensitiveParameter).
 */
final class PlaceOrderAction
{
    public const GATEWAY_ERROR_REASON = 'Servizio di pagamento non disponibile';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly SettleOrderPaymentAction $settlePayment,
    ) {}

    /**
     * @param  array{holder: string, number: string, expiry: string, cvc: string}  $payment
     *
     * @throws EmptyCartException
     * @throws InsufficientStockException
     * @throws PaymentFailedException
     */
    public function execute(User $user, int $addressId, #[\SensitiveParameter] array $payment): Order
    {
        $order = DB::transaction(fn (): Order => $this->createPendingOrder($user, $addressId));

        try {
            $result = $this->gateway->charge($order, $payment);
        } catch (Throwable $exception) {
            report($exception);

            $this->settlePayment->execute($order, PaymentResult::failure(self::GATEWAY_ERROR_REASON));

            throw $exception;
        }

        $order = $this->settlePayment->execute($order, $result);

        if (! $result->succeeded) {
            throw new PaymentFailedException($order->id);
        }

        return $order;
    }

    private function createPendingOrder(User $user, int $addressId): Order
    {
        $locked = ActiveUserLock::acquire($user);
        $cart = $locked->cart()->lockForUpdate()->first();
        $items = $cart === null
            ? new Collection
            : CartItem::query()->where('cart_id', $cart->id)->orderBy('id')->lockForUpdate()->get();

        if ($items->isEmpty()) {
            throw new EmptyCartException;
        }

        $address = $locked->addresses()->find($addressId)
            ?? throw ValidationException::withMessages(['address_id' => PlaceOrderRequest::INVALID_ADDRESS_MESSAGE]);
        $products = Product::withTrashed()
            ->whereIn('id', $items->pluck('product_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $items->each(fn (CartItem $item) => $item->setRelation('product', $products[$item->product_id]));

        self::ensurePurchasable($items);

        $summary = CartSummary::fromItems($items);
        $now = now();

        $order = $locked->orders()->create([
            'status' => OrderStatus::Pending,
            'subtotal' => $summary->subtotal,
            'shipping_cost' => $summary->shippingCost,
            'total' => $summary->total,
            'shipping_address' => [
                'full_name' => $address->full_name,
                'street' => $address->street,
                'postal_code' => $address->postal_code,
                'city' => $address->city,
                'province' => $address->province,
                'country' => $address->country,
                'phone' => $address->phone,
            ],
            'terms_accepted_at' => $now,
        ]);

        $order->update(['number' => Order::formatNumber($order->id, $order->created_at)]);

        foreach ($items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'product_sku' => $item->product->sku,
                'unit_price' => $item->product->price,
                'quantity' => $item->quantity,
                'line_total' => CartSummary::lineTotal($item),
            ]);

            $item->product->decrement('stock', $item->quantity);
        }

        $order->statusChanges()->forceCreate([
            'from_status' => null,
            'to_status' => OrderStatus::Pending,
            'changed_by' => null,
            'reason' => null,
            'created_at' => $now,
        ]);

        return $order;
    }

    /**
     * Every row must be purchasable (active, not deleted, in stock) and
     * within the stock. A product that cannot be bought has available 0.
     *
     * @param  Collection<int, CartItem>  $items
     */
    private static function ensurePurchasable(Collection $items): void
    {
        $problems = $items
            ->filter(fn (CartItem $item): bool => ! $item->is_purchasable || $item->exceeds_stock)
            ->map(fn (CartItem $item): array => [
                'product' => $item->product->name,
                'requested' => $item->quantity,
                'available' => $item->is_purchasable ? $item->product->stock : 0,
            ])
            ->values()
            ->all();

        if ($problems !== []) {
            throw new InsufficientStockException($problems);
        }
    }
}
