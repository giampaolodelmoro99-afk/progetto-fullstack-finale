<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
    actingAsAdmin();
});

function productWithImages(): array
{
    $product = Product::factory()->for(Category::factory())->create(['name' => 'Cuffie Aria', 'sku' => 'CDS-AUD-001', 'created_at' => '2026-09-01 10:00', 'updated_at' => '2026-09-01 10:00']);
    $first = ProductImage::factory()->for($product)->create(['path' => 'products/uno.webp', 'alt' => 'Uno', 'position' => 1]);
    $second = ProductImage::factory()->for($product)->create(['path' => 'products/due.webp', 'alt' => 'Due', 'position' => 2]);
    $third = ProductImage::factory()->for($product)->create(['path' => 'products/tre.webp', 'alt' => 'Tre', 'position' => 3]);

    return [$product, $first, $second, $third];
}

it('updates the fields and keeps the slug when the name changes', function () {
    [$product] = productWithImages();
    $category = Category::factory()->create(['name' => 'Scrivania']);

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload([
        'category_id' => $category->id,
        'name' => 'Cuffie Aria Studio',
        'price' => 39900,
        'stock' => 3,
        'is_active' => false,
        'specifications' => [['label' => 'Colore', 'value' => 'Grafite']],
    ]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Cuffie Aria Studio')
        ->assertJsonPath('data.slug', 'cuffie-aria')
        ->assertJsonPath('data.category', ['id' => $category->id, 'name' => 'Scrivania', 'slug' => 'scrivania'])
        ->assertJsonPath('data.stock_status', ['status' => 'low_stock', 'label' => 'Ultimi 3 pezzi'])
        ->assertJsonPath('data.updated_at', '2026-09-15T12:00:00+02:00')
        ->assertJsonCount(3, 'data.images');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Cuffie Aria Studio',
        'slug' => 'cuffie-aria',
        'price' => 39900,
        'stock' => 3,
        'is_active' => false,
    ]);
});

it('accepts the sku of the product itself', function () {
    [$product] = productWithImages();

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload(['sku' => 'CDS-AUD-001']))->assertOk();
});

it('rejects the sku of another product, also when deleted', function () {
    [$product] = productWithImages();
    Product::factory()->for(Category::factory())->trashed()->create(['sku' => 'CDS-AUD-002']);

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload(['sku' => 'CDS-AUD-002']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.sku.0', 'Questo SKU è già in uso');
});

it('saves the order and the alternative texts of the images', function () {
    [$product, $first, $second, $third] = productWithImages();

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload([
        'images' => [
            ['id' => $third->id, 'alt' => 'Cuffie Aria di fronte'],
            ['id' => $first->id, 'alt' => 'Uno'],
            ['id' => $second->id, 'alt' => 'Cuffie Aria di lato'],
        ],
    ]))
        ->assertOk()
        ->assertJsonPath('data.cover', ['url' => 'http://localhost:8000/storage/products/tre.webp', 'alt' => 'Cuffie Aria di fronte'])
        ->assertJsonPath('data.images', [
            ['id' => $third->id, 'url' => 'http://localhost:8000/storage/products/tre.webp', 'alt' => 'Cuffie Aria di fronte', 'position' => 1],
            ['id' => $first->id, 'url' => 'http://localhost:8000/storage/products/uno.webp', 'alt' => 'Uno', 'position' => 2],
            ['id' => $second->id, 'url' => 'http://localhost:8000/storage/products/due.webp', 'alt' => 'Cuffie Aria di lato', 'position' => 3],
        ]);

    $this->assertDatabaseHas('product_images', ['id' => $third->id, 'position' => 1, 'alt' => 'Cuffie Aria di fronte']);
    $this->assertDatabaseHas('product_images', ['id' => $first->id, 'position' => 2, 'alt' => 'Uno']);
    $this->assertDatabaseHas('product_images', ['id' => $second->id, 'position' => 3, 'alt' => 'Cuffie Aria di lato']);
});

it('leaves the images untouched without the images field', function () {
    [$product, $first] = productWithImages();

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload())->assertOk();

    $this->assertDatabaseHas('product_images', ['id' => $first->id, 'position' => 1, 'alt' => 'Uno']);
});

it('rejects images that are not exactly the images of the product', function (string $case, string $field) {
    [$product, $first, $second, $third] = productWithImages();
    $foreign = ProductImage::factory()->for(Product::factory()->for(Category::factory()))->create();
    $row = fn (ProductImage $image, ?string $alt = null): array => ['id' => $image->id, 'alt' => $alt ?? $image->alt];

    $images = match ($case) {
        'foreign' => [$row($first), $row($second), $row($foreign)],
        'missing' => [$row($first), $row($second)],
        'repeated' => [$row($first), $row($first), $row($second), $row($third)],
        'extra' => [$row($first), $row($second), $row($third), $row($foreign)],
        'empty alt' => [$row($first, ''), $row($second), $row($third)],
        'long alt' => [$row($first, str_repeat('a', 256)), $row($second), $row($third)],
    };

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload([
        'name' => 'Nome cambiato',
        'images' => $images,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Cuffie Aria']);
    $this->assertDatabaseHas('product_images', ['id' => $first->id, 'position' => 1, 'alt' => 'Uno']);
})->with([
    'an image of another product' => ['foreign', 'images'],
    'a missing image' => ['missing', 'images'],
    'a repeated image' => ['repeated', 'images.0.id'],
    'an extra image of another product' => ['extra', 'images'],
    'a missing alternative text' => ['empty alt', 'images.0.alt'],
    'an alternative text of 256 characters' => ['long alt', 'images.0.alt'],
]);

it('explains in italian that the images do not match', function () {
    [$product, $first] = productWithImages();

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload([
        'images' => [['id' => $first->id, 'alt' => 'Uno']],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.images.0', 'Le immagini non corrispondono a quelle del prodotto: ricarica la pagina.');
});

it('checks the images again under the lock and rolls back when an upload arrived after the validation', function () {
    [$product, $first, $second, $third] = productWithImages();
    Product::updating(function (Product $updating) {
        ProductImage::factory()->for($updating)->create(['path' => 'products/arrivata-dopo.webp', 'position' => 4]);
    });

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload([
        'name' => 'Nome cambiato',
        'images' => [
            ['id' => $third->id, 'alt' => 'Tre'],
            ['id' => $first->id, 'alt' => 'Uno'],
            ['id' => $second->id, 'alt' => 'Due'],
        ],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.images', ['Le immagini non corrispondono a quelle del prodotto: ricarica la pagina.']);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Cuffie Aria']);
    $this->assertDatabaseHas('product_images', ['id' => $first->id, 'position' => 1]);
    $this->assertDatabaseHas('product_images', ['id' => $third->id, 'position' => 3]);
    $this->assertDatabaseMissing('product_images', ['path' => 'products/arrivata-dopo.webp']);
});

it('answers 404 for a deleted product', function () {
    $product = Product::factory()->for(Category::factory())->trashed()->create();

    $this->patchJson("/api/v1/admin/products/{$product->id}", adminProductPayload())->assertNotFound();
});
