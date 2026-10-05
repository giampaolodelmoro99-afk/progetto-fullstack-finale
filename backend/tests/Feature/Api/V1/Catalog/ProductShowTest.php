<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
});

it('shows the product page with descriptions, technical sheet and gallery', function () {
    $product = Product::factory()->for(Category::factory()->create(['name' => 'Audio']))->create([
        'name' => 'Cuffie Aria',
        'short_description' => 'Cuffie wireless con cancellazione del rumore.',
        'description' => "Suono pulito e bilanciato.\n\nAutonomia per una settimana.",
        'specifications' => [
            ['label' => 'Peso', 'value' => '250 g'],
            ['label' => 'Autonomia', 'value' => '30 ore'],
            ['label' => 'Connessione', 'value' => 'Bluetooth 5.3'],
        ],
        'price' => 34900,
        'stock' => 25,
        'created_at' => '2026-09-05 10:00',
    ]);
    ProductImage::factory()->for($product)->create(['path' => 'products/cuffie-aria-lato.webp', 'alt' => 'Cuffie Aria di lato', 'position' => 2]);
    ProductImage::factory()->for($product)->create(['path' => 'products/cuffie-aria.webp', 'alt' => 'Cuffie Aria grafite', 'position' => 1]);

    $this->getJson('/api/v1/products/cuffie-aria')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $product->id,
                'name' => 'Cuffie Aria',
                'slug' => 'cuffie-aria',
                'price' => 34900,
                'is_new' => true,
                'availability' => ['status' => 'available', 'label' => 'Disponibile'],
                'category' => ['name' => 'Audio', 'slug' => 'audio'],
                'cover' => ['url' => 'http://localhost:8000/storage/products/cuffie-aria.webp', 'alt' => 'Cuffie Aria grafite'],
                'short_description' => 'Cuffie wireless con cancellazione del rumore.',
                'description' => "Suono pulito e bilanciato.\n\nAutonomia per una settimana.",
                'specifications' => [
                    ['label' => 'Peso', 'value' => '250 g'],
                    ['label' => 'Autonomia', 'value' => '30 ore'],
                    ['label' => 'Connessione', 'value' => 'Bluetooth 5.3'],
                ],
                'images' => [
                    ['url' => 'http://localhost:8000/storage/products/cuffie-aria.webp', 'alt' => 'Cuffie Aria grafite'],
                    ['url' => 'http://localhost:8000/storage/products/cuffie-aria-lato.webp', 'alt' => 'Cuffie Aria di lato'],
                ],
                'max_quantity' => 10,
            ],
        ]);
});

it('caps the max quantity at the stock and at ten', function (int $stock, int $maxQuantity) {
    Product::factory()->for(Category::factory())->create(['name' => 'Radio Onda', 'stock' => $stock]);

    $this->getJson('/api/v1/products/radio-onda')
        ->assertOk()
        ->assertJsonPath('data.max_quantity', $maxQuantity);
})->with([
    'stock 3' => [3, 3],
    'stock 10' => [10, 10],
    'stock 25' => [25, 10],
]);

it('shows an out of stock product, which cannot be bought', function () {
    Product::factory()->for(Category::factory())->outOfStock()->create(['name' => 'Radio Onda Portatile']);

    $this->getJson('/api/v1/products/radio-onda-portatile')
        ->assertOk()
        ->assertJsonPath('data.availability', ['status' => 'out_of_stock', 'label' => 'Esaurito'])
        ->assertJsonPath('data.max_quantity', 0);
});

it('gives no cover and an empty gallery to a product without images', function () {
    Product::factory()->for(Category::factory())->create(['name' => 'Lampada Faro', 'specifications' => []]);

    $this->getJson('/api/v1/products/lampada-faro')
        ->assertOk()
        ->assertJsonPath('data.cover', null)
        ->assertJsonPath('data.images', [])
        ->assertJsonPath('data.specifications', []);
});

it('never exposes the admin data of a product', function () {
    Product::factory()->for(Category::factory())->create(['name' => 'Orologio Meridiana']);

    $this->getJson('/api/v1/products/orologio-meridiana')
        ->assertOk()
        ->assertJsonMissingPath('data.sku')
        ->assertJsonMissingPath('data.stock')
        ->assertJsonMissingPath('data.stock_status')
        ->assertJsonMissingPath('data.is_active')
        ->assertJsonMissingPath('data.category_id')
        ->assertJsonMissingPath('data.deleted_at');
});

it('answers 404 for a deactivated, a deleted or an unknown product', function (string $state) {
    $factory = Product::factory()->for(Category::factory());
    $factory = match ($state) {
        'inactive' => $factory->inactive(),
        'trashed' => $factory->trashed(),
        default => $factory,
    };
    $factory->create(['name' => 'Supporto Colle']);

    $slug = $state === 'unknown' ? 'supporto-inesistente' : 'supporto-colle';

    $this->getJson('/api/v1/products/'.$slug)
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);
})->with(['inactive', 'trashed', 'unknown']);

it('answers 404 for a deactivated product to an admin too', function () {
    Product::factory()->for(Category::factory())->inactive()->create(['name' => 'Supporto Colle']);
    actingAsAdmin();

    $this->getJson('/api/v1/products/supporto-colle')->assertNotFound();
});
