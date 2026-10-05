<?php

namespace App\Http\Requests\Admin\Product;

use App\Models\Product;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * A new product, without images: they are uploaded once the product exists.
 */
final class StoreProductRequest extends SaveProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    protected function uniqueSku(): Unique
    {
        return Rule::unique('products', 'sku');
    }
}
