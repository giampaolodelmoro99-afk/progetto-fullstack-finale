<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ProductImageResource as ShopProductImageResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product of the admin area: SKU, real stock, the stock status with the
 * restocking threshold and the active state. The list loads the cover;
 * the detail loads the images, and the cover is the first of them.
 *
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'short_description' => $this->short_description,
            'description' => $this->description,
            'specifications' => $this->specifications ?? [],
            'price' => $this->price,
            'stock' => $this->stock,
            'stock_status' => $this->stock_status,
            'is_active' => $this->is_active,
            'cover' => $this->coverImage(),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function coverImage(): mixed
    {
        if ($this->resource->relationLoaded('images')) {
            return $this->images->isEmpty() ? null : ShopProductImageResource::make($this->images->first());
        }

        return $this->whenLoaded('cover', fn () => $this->cover === null ? null : ShopProductImageResource::make($this->cover));
    }
}
