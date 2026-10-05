<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Potrebbero piacerti" of the product page: up to four active products of
 * the same category, in stock, newest first. Fewer are returned when the
 * category has fewer; other categories never fill the gap.
 */
final class GetRelatedProductsAction
{
    private const LIMIT = 4;

    /**
     * @return Collection<int, Product>
     */
    public function execute(Product $product): Collection
    {
        return Product::query()
            ->active()
            ->inStock()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->getKey())
            ->sortBy('newest')
            ->limit(self::LIMIT)
            ->with(['category', 'cover'])
            ->get();
    }
}
