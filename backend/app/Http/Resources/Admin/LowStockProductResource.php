<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ProductImageResource as ShopProductImageResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product of "Scorte basse" in the dashboard: real stock, stock status
 * with the restocking threshold and cover.
 *
 * @mixin Product
 */
final class LowStockProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stock' => $this->stock,
            'stock_status' => $this->stock_status,
            'cover' => $this->cover === null ? null : ShopProductImageResource::make($this->cover),
        ];
    }
}
