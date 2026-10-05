<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limits of the price slider: lowest and highest price of the active
 * products of the category, or of the whole catalog without a category.
 * The other filters never change it, so the slider stays still while the
 * user moves it.
 */
final class GetPriceRangeAction
{
    /**
     * @return array{min: ?int, max: ?int}
     */
    public function execute(?string $categorySlug = null): array
    {
        $range = Product::query()
            ->active()
            ->when(filled($categorySlug), fn (Builder $query) => $query->inCategory($categorySlug))
            ->toBase()
            ->selectRaw('min(price) as min_price, max(price) as max_price')
            ->first();

        return [
            'min' => $range?->min_price !== null ? (int) $range->min_price : null,
            'max' => $range?->max_price !== null ? (int) $range->max_price : null,
        ];
    }
}
