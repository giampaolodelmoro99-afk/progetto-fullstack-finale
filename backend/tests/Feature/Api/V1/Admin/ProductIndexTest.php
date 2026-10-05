<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
    actingAsAdmin();
});

function responseSkus(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('sku')->all();
}

it('lists active and deactivated products by sku, without the deleted ones', function () {
    $audio = Category::factory()->create(['name' => 'Audio']);
    Product::factory()->for($audio)->create(['sku' => 'CDS-AUD-002']);
    Product::factory()->for($audio)->inactive()->create(['sku' => 'CDS-AUD-001']);
    Product::factory()->for($audio)->create(['sku' => 'CDS-AUD-003']);
    Product::factory()->for($audio)->trashed()->create(['sku' => 'CDS-AUD-000']);

    $response = $this->getJson('/api/v1/admin/products')->assertOk();

    expect(responseSkus($response))->toBe(['CDS-AUD-001', 'CDS-AUD-002', 'CDS-AUD-003'])
        ->and($response->json('meta.total'))->toBe(3)
        ->and($response->json('meta.per_page'))->toBe(10);
});

it('gives the admin shape of a product in the list, with the cover and without the images', function () {
    $product = Product::factory()->for(Category::factory()->create(['name' => 'Audio']))->create([
        'name' => 'Cuffie Aria',
        'sku' => 'CDS-AUD-001',
        'short_description' => 'Cuffie wireless con cancellazione del rumore.',
        'description' => 'Suono pulito e bilanciato.',
        'specifications' => [['label' => 'Peso', 'value' => '250 g']],
        'price' => 34900,
        'stock' => 4,
        'is_active' => false,
    ]);
    ProductImage::factory()->for($product)->create(['path' => 'products/retro.webp', 'alt' => 'Retro', 'position' => 2]);
    ProductImage::factory()->for($product)->create(['path' => 'products/fronte.webp', 'alt' => 'Fronte', 'position' => 1]);

    $this->getJson('/api/v1/admin/products')
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => [['id', 'name', 'slug', 'sku', 'category', 'short_description', 'description', 'specifications', 'price', 'stock', 'stock_status', 'is_active', 'cover', 'updated_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
        ])
        ->assertJsonPath('data.0', [
            'id' => $product->id,
            'name' => 'Cuffie Aria',
            'slug' => 'cuffie-aria',
            'sku' => 'CDS-AUD-001',
            'category' => ['id' => $product->category_id, 'name' => 'Audio', 'slug' => 'audio'],
            'short_description' => 'Cuffie wireless con cancellazione del rumore.',
            'description' => 'Suono pulito e bilanciato.',
            'specifications' => [['label' => 'Peso', 'value' => '250 g']],
            'price' => 34900,
            'stock' => 4,
            'stock_status' => ['status' => 'low_stock', 'label' => 'Ultimi 4 pezzi'],
            'is_active' => false,
            'cover' => ['url' => 'http://localhost:8000/storage/products/fronte.webp', 'alt' => 'Fronte'],
            'updated_at' => '2026-09-15T12:00:00+02:00',
        ]);
});

it('gives a null cover to a product without images', function () {
    Product::factory()->for(Category::factory())->create();

    $this->getJson('/api/v1/admin/products')
        ->assertOk()
        ->assertJsonPath('data.0.cover', null);
});

it('searches by name and by sku', function (string $search, array $skus) {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create(['name' => 'Cuffie Aria', 'sku' => 'CDS-AUD-001']);
    Product::factory()->for($category)->create(['name' => 'Diffusore Eco', 'sku' => 'CDS-AUD-002']);
    Product::factory()->for($category)->create(['name' => 'Caricatore Brezza 65 W', 'sku' => 'CDS-RIC-010']);

    expect(responseSkus($this->getJson('/api/v1/admin/products?search='.urlencode($search))->assertOk()))->toBe($skus);
})->with([
    'part of the name' => ['aria', ['CDS-AUD-001']],
    'part of the sku' => ['RIC-0', ['CDS-RIC-010']],
    'common sku prefix' => ['CDS-AUD', ['CDS-AUD-001', 'CDS-AUD-002']],
    'no match' => ['lampada', []],
]);

it('filters by category and by status', function (string $query, array $skus) {
    $audio = Category::factory()->create(['name' => 'Audio']);
    $desk = Category::factory()->create(['name' => 'Scrivania']);
    Product::factory()->for($audio)->create(['sku' => 'CDS-AUD-001']);
    Product::factory()->for($audio)->inactive()->create(['sku' => 'CDS-AUD-002']);
    Product::factory()->for($desk)->create(['sku' => 'CDS-SCR-001']);
    Product::factory()->for($desk)->inactive()->create(['sku' => 'CDS-SCR-002']);

    expect(responseSkus($this->getJson('/api/v1/admin/products?'.$query)->assertOk()))->toBe($skus);
})->with([
    'category' => ['category=audio', ['CDS-AUD-001', 'CDS-AUD-002']],
    'active' => ['status=active', ['CDS-AUD-001', 'CDS-SCR-001']],
    'inactive' => ['status=inactive', ['CDS-AUD-002', 'CDS-SCR-002']],
    'category and status' => ['category=scrivania&status=inactive', ['CDS-SCR-002']],
]);

it('paginates with 10, 25 or 50 rows', function (int $perPage, int $rows) {
    Product::factory()->count(30)->for(Category::factory())->create();

    $response = $this->getJson("/api/v1/admin/products?per_page={$perPage}")->assertOk();

    expect($response->json('data'))->toHaveCount($rows)
        ->and($response->json('meta.per_page'))->toBe($perPage);
})->with([
    [10, 10],
    [25, 25],
    [50, 30],
]);

it('orders products with the same sku prefix by sku, across pages', function () {
    $category = Category::factory()->create();
    foreach (['CDS-AUD-011', 'CDS-AUD-003', 'CDS-AUD-007', 'CDS-AUD-001', 'CDS-AUD-005', 'CDS-AUD-009', 'CDS-AUD-002', 'CDS-AUD-010', 'CDS-AUD-004', 'CDS-AUD-008', 'CDS-AUD-006'] as $sku) {
        Product::factory()->for($category)->create(['sku' => $sku]);
    }

    expect(responseSkus($this->getJson('/api/v1/admin/products?page=2')->assertOk()))->toBe(['CDS-AUD-011']);
});

it('rejects invalid filters', function (string $query, string $field) {
    Category::factory()->create(['name' => 'Audio']);

    $this->getJson('/api/v1/admin/products?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'unknown category' => ['category=cucina', 'category'],
    'unknown status' => ['status=deleted', 'status'],
    'per page of the shop' => ['per_page=12', 'per_page'],
    'page zero' => ['page=0', 'page'],
    'search too long' => ['search='.str_repeat('a', 101), 'search'],
]);

it('answers in italian for an unknown category', function () {
    $this->getJson('/api/v1/admin/products?category=cucina')
        ->assertUnprocessable()
        ->assertJsonPath('errors.category.0', 'Questa categoria non esiste.');
});

it('computes the stock status with the admin threshold', function (int $stock, string $status, string $label) {
    Product::factory()->for(Category::factory())->create(['stock' => $stock]);

    $this->getJson('/api/v1/admin/products')
        ->assertOk()
        ->assertJsonPath('data.0.stock_status', ['status' => $status, 'label' => $label]);
})->with([
    [0, 'out_of_stock', 'Esaurito'],
    [1, 'low_stock', 'Ultimo pezzo'],
    [4, 'low_stock', 'Ultimi 4 pezzi'],
    [5, 'low_stock', 'Ultimi 5 pezzi'],
    [6, 'available', 'Disponibile'],
]);

it('lists the products with the same number of queries for 2 and for 10 products', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/admin/products')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    $withImages = function (int $products): void {
        Product::factory()->count($products)->for(Category::factory())->create()
            ->each(fn (Product $product) => ProductImage::factory()->count(2)->for($product)->create());
    };

    $withImages(2);
    $few = $count();
    $withImages(8);

    expect($count())->toBe($few);
});
