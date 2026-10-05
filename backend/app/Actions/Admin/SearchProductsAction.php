<?php

namespace App\Actions\Admin;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin product list: active and deactivated products, not the deleted
 * ones, by SKU and then id. Returns the query, so the caller paginates it.
 */
final class SearchProductsAction
{
    /**
     * @param  array{search?: string, category?: string, status?: string}  $filters  validated filters
     * @return Builder<Product>
     */
    public function execute(array $filters): Builder
    {
        return Product::query()
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->searchByNameOrSku($filters['search']))
            ->when(filled($filters['category'] ?? null), fn (Builder $query) => $query->inCategory($filters['category']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('sku')
            ->orderBy('id');
    }
}
