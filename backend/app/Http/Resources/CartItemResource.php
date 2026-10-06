<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Support\CartSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the cart with the current price of the product. A row that
 * cannot be bought (deactivated, deleted or out of stock product) keeps
 * its unit price but has no line total.
 *
 * @mixin CartItem
 */
final class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'max_quantity' => $product->max_quantity,
            'exceeds_stock' => $this->exceeds_stock,
            'is_purchasable' => $this->is_purchasable,
            'unit_price' => $product->price,
            'line_total' => CartSummary::lineTotal($this->resource),
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
                'category' => [
                    'name' => $product->category->name,
                    'slug' => $product->category->slug,
                ],
                'cover' => $product->cover === null ? null : ProductImageResource::make($product->cover),
                'availability' => $product->availability,
            ],
        ];
    }
}
