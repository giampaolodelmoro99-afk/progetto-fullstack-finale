<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Public catalog query: active, non deleted products with the filters of
 * the catalog. Returns the query, so callers paginate or limit it.
 */
final class SearchProductsAction
{
    /**
     * @param  array{search?: string, category?: string, min_price?: int, max_price?: int, in_stock?: bool, new?: bool, sort?: string}  $filters  validated filters, prices in cents
     * @return Builder<Product>
     */
    public function execute(array $filters): Builder
    {
        return Product::query()
            ->active()
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->search($filters['search']))
            ->when(filled($filters['category'] ?? null), fn (Builder $query) => $query->inCategory($filters['category']))
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null)
            ->when($filters['in_stock'] ?? false, fn (Builder $query) => $query->inStock())
            ->when($filters['new'] ?? false, fn (Builder $query) => $query->newArrivals())
            ->sortBy($filters['sort'] ?? 'newest');
    }
}
