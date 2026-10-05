<?php

namespace App\Http\Resources\Admin;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category of the admin area, with the counts loaded by
 * Category::withProductCounts(): products_count leaves out the deleted
 * products, while can_delete counts them too.
 *
 * @mixin Category
 */
final class CategoryResource extends JsonResource
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
            'description' => $this->description,
            'products_count' => $this->whenCounted('products'),
            'can_delete' => $this->whenCounted('all_products', fn (int $count): bool => $count === 0),
        ];
    }
}
