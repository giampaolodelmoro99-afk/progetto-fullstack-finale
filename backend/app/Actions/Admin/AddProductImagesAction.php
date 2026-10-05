<?php

namespace App\Actions\Admin;

use App\Models\Product;
use App\Support\WebpImageEncoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Appends images to a product. The uploaded file is never stored: it is
 * re-encoded as WebP (WebpImageEncoder) and saved as products/{uuid}.webp,
 * a name that cannot be guessed and does not collide. Files are written
 * before the transaction and removed if it fails; the rows are appended
 * after the last position under the lock of the product, with the product
 * name as the initial alternative text.
 */
final class AddProductImagesAction
{
    public const DIRECTORY = 'products';

    /**
     * @param  list<UploadedFile>  $files
     */
    public function execute(Product $product, array $files): Product
    {
        $encoded = array_map(fn (UploadedFile $file): string => $this->encode($file), $files);

        $disk = Storage::disk('public');
        $paths = [];

        try {
            foreach ($encoded as $bytes) {
                $path = self::DIRECTORY.'/'.Str::uuid().'.webp';
                $disk->put($path, $bytes);
                $paths[] = $path;
            }

            DB::transaction(function () use ($product, $paths): void {
                Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

                $position = (int) $product->images()->max('position');

                foreach ($paths as $path) {
                    $product->images()->create([
                        'path' => $path,
                        'alt' => $product->name,
                        'position' => ++$position,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            $disk->delete($paths);

            throw $exception;
        }

        return $product;
    }

    private function encode(UploadedFile $file): string
    {
        try {
            return WebpImageEncoder::encode($file->getRealPath());
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'images' => "Non è stato possibile leggere l'immagine {$file->getClientOriginalName()}.",
            ]);
        }
    }
}
