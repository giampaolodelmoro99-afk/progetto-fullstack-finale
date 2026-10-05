<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
    actingAsAdmin();
});

it('shows a deactivated product with its images in order', function () {
    $product = Product::factory()->for(Category::factory()->create(['name' => 'Audio']))->inactive()->create([
        'name' => 'Cuffie Aria',
        'sku' => 'CDS-AUD-001',
        'short_description' => 'Cuffie wireless con cancellazione del rumore.',
        'description' => null,
        'specifications' => [
            ['label' => 'Peso', 'value' => '250 g'],
            ['label' => 'Autonomia', 'value' => '30 ore'],
        ],
        'price' => 34900,
        'stock' => 0,
    ]);
    $side = ProductImage::factory()->for($product)->create(['path' => 'products/lato.webp', 'alt' => 'Di lato', 'position' => 2]);
    $front = ProductImage::factory()->for($product)->create(['path' => 'products/fronte.webp', 'alt' => 'Di fronte', 'position' => 1]);

    $this->getJson("/api/v1/admin/products/{$product->id}")
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $product->id,
                'name' => 'Cuffie Aria',
                'slug' => 'cuffie-aria',
                'sku' => 'CDS-AUD-001',
                'category' => ['id' => $product->category_id, 'name' => 'Audio', 'slug' => 'audio'],
                'short_description' => 'Cuffie wireless con cancellazione del rumore.',
                'description' => null,
                'specifications' => [
                    ['label' => 'Peso', 'value' => '250 g'],
                    ['label' => 'Autonomia', 'value' => '30 ore'],
                ],
                'price' => 34900,
                'stock' => 0,
                'stock_status' => ['status' => 'out_of_stock', 'label' => 'Esaurito'],
                'is_active' => false,
                'cover' => ['url' => 'http://localhost:8000/storage/products/fronte.webp', 'alt' => 'Di fronte'],
                'images' => [
                    ['id' => $front->id, 'url' => 'http://localhost:8000/storage/products/fronte.webp', 'alt' => 'Di fronte', 'position' => 1],
                    ['id' => $side->id, 'url' => 'http://localhost:8000/storage/products/lato.webp', 'alt' => 'Di lato', 'position' => 2],
                ],
                'updated_at' => '2026-09-15T12:00:00+02:00',
            ],
        ]);
});

it('gives a null cover and no images to a product without images', function () {
    $product = Product::factory()->for(Category::factory())->create();

    $this->getJson("/api/v1/admin/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.cover', null)
        ->assertJsonPath('data.images', []);
});

it('answers 404 for a deleted or missing product', function () {
    $deleted = Product::factory()->for(Category::factory())->trashed()->create();

    $this->getJson("/api/v1/admin/products/{$deleted->id}")
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);
    $this->getJson('/api/v1/admin/products/999999')->assertNotFound();
});
