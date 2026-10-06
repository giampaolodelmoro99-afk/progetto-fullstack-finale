<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of an order: name, SKU and prices always from the snapshot. The
 * cover is the current one of the product, null when the product was
 * deleted (soft or for good): the frontend shows its fallback image.
 *
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->product?->cover;

        return [
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total,
            'cover' => $cover === null ? null : ProductImageResource::make($cover),
        ];
    }
}
