<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product of the public catalog, as listed and in the related products.
 * Stock, SKU and active state stay in the admin resource: customers see
 * only the computed availability.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'price' => $this->price,
            'is_new' => $this->is_new,
            'availability' => $this->availability,
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'cover' => $this->whenLoaded('cover', fn () => ProductImageResource::make($this->cover)),
        ];
    }
}
