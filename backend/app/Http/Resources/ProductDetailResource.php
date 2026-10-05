<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * The product page: the fields of the list plus descriptions, technical
 * sheet, gallery and the maximum quantity of the quantity selector. The
 * cover is the first image of the gallery, already loaded: images() sorts
 * by position and id like Product::cover(), so it is the same image the
 * list shows.
 *
 * @mixin Product
 */
final class ProductDetailResource extends ProductResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'cover' => $this->whenLoaded('images', fn () => $this->images->isEmpty()
                ? null
                : ProductImageResource::make($this->images->first())),
            'short_description' => $this->short_description,
            'description' => $this->description,
            'specifications' => $this->specifications ?? [],
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'max_quantity' => $this->max_quantity,
        ];
    }
}
