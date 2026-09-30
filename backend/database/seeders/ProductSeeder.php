<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Products from data/products.php, with created_at relative to the seeding
 * date and three images each (front as cover, angle, detail) copied to the
 * public disk. The images folder is emptied first, so a fresh seeding never
 * leaves orphan files.
 */
class ProductSeeder extends Seeder
{
    public const IMAGES_DIRECTORY = 'products';

    private const VIEWS = ['front', 'angle', 'detail'];

    public function run(): void
    {
        $disk = Storage::disk('public');
        $disk->deleteDirectory(self::IMAGES_DIRECTORY);

        $categories = Category::pluck('id', 'slug');

        foreach (require __DIR__.'/data/products.php' as $index => $data) {
            $createdAt = today()->subDays($data['days_ago'])->setTime(9 + $index % 10, ($index * 7) % 60);

            $product = new Product;
            $product->forceFill([
                'category_id' => $categories[$data['category']],
                'name' => $data['name'],
                'sku' => $data['sku'],
                'short_description' => $data['short_description'],
                'description' => implode("\n\n", $data['description']),
                'specifications' => $data['specifications'],
                'price' => $data['price'],
                'stock' => $data['stock'],
                'is_active' => $data['is_active'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            foreach (self::VIEWS as $position => $view) {
                $path = self::IMAGES_DIRECTORY."/{$product->slug}-{$view}.webp";

                $disk->put($path, File::get(
                    database_path("seeders/assets/products/{$data['illustration']}-{$data['finish']}-{$view}.webp"),
                ));

                $product->images()->create([
                    'path' => $path,
                    'alt' => $product->name,
                    'position' => $position + 1,
                ]);
            }
        }
    }
}
