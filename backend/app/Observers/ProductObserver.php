<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Str;

final class ProductObserver
{
    private const SLUG_MAX_LENGTH = 255;

    /**
     * The slug is derived from the name only on creation and is unique among all
     * products, soft deleted included: on collision it gets a -2, -3… suffix, and
     * the base is truncated so that slug and suffix fit in the column.
     */
    public function creating(Product $product): void
    {
        $base = rtrim(substr(Str::slug($product->name) ?: 'prodotto', 0, self::SLUG_MAX_LENGTH), '-');
        $slug = $base;
        $counter = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $counter++;
            $suffix = "-{$counter}";
            $slug = rtrim(substr($base, 0, self::SLUG_MAX_LENGTH - strlen($suffix)), '-').$suffix;
        }

        $product->slug = $slug;
    }
}
