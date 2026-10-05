<?php

namespace App\Actions\Admin;

use App\Exceptions\Catalog\CategoryHasProductsException;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a category without products, soft deleted ones included: the
 * foreign key of products would refuse it anyway, but with a 500. The
 * category row is locked, so a product inserted at the same time (its
 * foreign key check reads the category) waits for the decision.
 */
final class DeleteCategoryAction
{
    public function execute(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            Category::query()->whereKey($category->getKey())->lockForUpdate()->first();

            if ($category->products()->exists()) {
                throw CategoryHasProductsException::withProducts();
            }

            if ($category->products()->onlyTrashed()->exists()) {
                throw CategoryHasProductsException::withDeletedProducts();
            }

            $category->delete();
        });
    }
}
