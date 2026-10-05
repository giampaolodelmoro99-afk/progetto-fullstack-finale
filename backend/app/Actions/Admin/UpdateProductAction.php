<?php

namespace App\Actions\Admin;

use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the product form and, when sent, the order and the alternative
 * texts of the images. The product row is locked, as in the image upload
 * and deletion, and the image ids are checked again under the lock: an
 * upload finished after the validation would otherwise be left out.
 */
final class UpdateProductAction
{
    /**
     * @param  array<string, mixed>  $data  validated fields, with images as a list of {id, alt}
     */
    public function execute(Product $product, array $data): Product
    {
        $images = $data['images'] ?? null;
        unset($data['images']);

        DB::transaction(function () use ($product, $data, $images): void {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $product->update($data);

            if ($images !== null) {
                $this->reorderImages($product, $images);
            }
        });

        return $product;
    }

    /**
     * @param  list<array{id: int|string, alt: string}>  $images
     */
    private function reorderImages(Product $product, array $images): void
    {
        $current = $product->images()->get()->keyBy('id');
        $sent = array_map(fn (array $image): int => (int) $image['id'], $images);

        if (count($sent) !== $current->count() || array_diff($sent, $current->keys()->all()) !== []) {
            throw ValidationException::withMessages(['images' => UpdateProductRequest::IMAGES_MISMATCH_MESSAGE]);
        }

        foreach (array_values($images) as $index => $image) {
            $current[(int) $image['id']]->update([
                'position' => $index + 1,
                'alt' => $image['alt'],
            ]);
        }
    }
}
