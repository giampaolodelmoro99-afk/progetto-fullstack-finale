<?php

namespace App\Actions\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes an image right away: the row goes and the positions of the
 * others are compacted from 1, under the lock of the product. The file is
 * removed from the disk only after the commit, so a rollback never leaves
 * a row without its file.
 */
final class DeleteProductImageAction
{
    public function execute(Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($product, $image): void {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $image->delete();

            $product->images()->get()->each(
                fn (ProductImage $remaining, int $index) => $remaining->update(['position' => $index + 1]),
            );
        });

        Storage::disk('public')->delete($image->path);
    }
}
