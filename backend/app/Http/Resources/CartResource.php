<?php

namespace App\Http\Resources;

use App\Models\Cart;
use App\Support\CartSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The cart of the customer: rows, totals on the purchasable rows and the
 * checkout flag. The frontend shows these values and never computes them;
 * the checkout checks them again.
 *
 * @mixin Cart
 */
final class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = CartSummary::fromItems($this->items);

        return [
            'items' => CartItemResource::collection($this->items),
            'summary' => [
                'items_count' => $summary->itemsCount,
                'subtotal' => $summary->subtotal,
                'shipping_cost' => $summary->shippingCost,
                'total' => $summary->total,
                'free_shipping_threshold' => $summary->freeShippingThreshold,
                'free_shipping_remaining' => $summary->freeShippingRemaining,
            ],
            'can_checkout' => $summary->canCheckout,
        ];
    }
}
