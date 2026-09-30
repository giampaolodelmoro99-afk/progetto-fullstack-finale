<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Appended after the last image of the product, with the product name as alt.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'path' => 'products/'.Str::uuid().'.webp',
            'alt' => fn (array $attributes) => Product::withTrashed()->find($attributes['product_id'])->name,
            'position' => fn (array $attributes) => (int) ProductImage::where('product_id', $attributes['product_id'])->max('position') + 1,
        ];
    }
}
