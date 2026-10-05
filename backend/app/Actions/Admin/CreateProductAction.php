<?php

namespace App\Actions\Admin;

use App\Models\Product;

/**
 * A new product without images; ProductObserver generates the slug.
 */
final class CreateProductAction
{
    /**
     * @param  array<string, mixed>  $data  validated fields of the product form
     */
    public function execute(array $data): Product
    {
        return Product::create($data);
    }
}
