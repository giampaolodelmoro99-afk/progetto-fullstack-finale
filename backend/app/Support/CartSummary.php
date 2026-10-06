<?php

namespace App\Support;

use App\Models\CartItem;
use Illuminate\Support\Collection;

/**
 * Totals of a cart, always on the current prices. The item count sums every
 * row, the ones that cannot be bought included; subtotal, shipping and total
 * sum only the purchasable rows, a row above the stock included (it blocks
 * the checkout instead). With nothing to ship (subtotal 0) shipping is free
 * and the free shipping remainder is null, so the summary shows no "Ti
 * mancano" bar next to a zero shipping cost. The rows need their product
 * loaded. The same class gives the totals of the order at checkout.
 */
final class CartSummary
{
    private function __construct(
        public readonly int $itemsCount,
        public readonly int $subtotal,
        public readonly int $shippingCost,
        public readonly int $total,
        public readonly int $freeShippingThreshold,
        public readonly ?int $freeShippingRemaining,
        public readonly bool $canCheckout,
    ) {}

    /**
     * @param  Collection<int, CartItem>  $items
     */
    public static function fromItems(Collection $items): self
    {
        $threshold = config('shop.free_shipping_threshold');
        $subtotal = (int) $items->sum(fn (CartItem $item): int => self::lineTotal($item) ?? 0);
        $shippingCost = $subtotal === 0 || $subtotal >= $threshold ? 0 : config('shop.shipping_cost');

        return new self(
            itemsCount: (int) $items->sum('quantity'),
            subtotal: $subtotal,
            shippingCost: $shippingCost,
            total: $subtotal + $shippingCost,
            freeShippingThreshold: $threshold,
            freeShippingRemaining: $subtotal === 0 ? null : max(0, $threshold - $subtotal),
            canCheckout: $items->isNotEmpty()
                && $items->every(fn (CartItem $item): bool => $item->is_purchasable && ! $item->exceeds_stock),
        );
    }

    /**
     * Current price times quantity, null for a row that cannot be bought.
     */
    public static function lineTotal(CartItem $item): ?int
    {
        return $item->is_purchasable ? $item->product->price * $item->quantity : null;
    }
}
