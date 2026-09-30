<?php

use App\Models\Category;
use App\Models\Product;

it('keeps only active products, never the soft deleted ones', function () {
    $active = Product::factory()->create();
    Product::factory()->inactive()->create();
    Product::factory()->trashed()->create();

    expect(Product::active()->pluck('id')->all())->toBe([$active->id]);
});

it('keeps only products in stock', function () {
    $inStock = Product::factory()->lastUnits(1)->create();
    Product::factory()->outOfStock()->create();

    expect(Product::inStock()->pluck('id')->all())->toBe([$inStock->id]);
});

it('keeps the products created in the last thirty days', function (string $createdAt, bool $included) {
    $this->travelTo('2026-09-29 12:00');
    Product::factory()->create(['created_at' => $createdAt]);

    expect(Product::newArrivals()->exists())->toBe($included);
})->with([
    'created 29 days ago' => ['2026-08-31 12:00', true],
    'created 31 days ago' => ['2026-08-29 12:00', false],
]);

it('searches in the name and in the short description', function () {
    $byName = Product::factory()->create(['name' => 'Cuffie Aria', 'short_description' => 'Suono pulito.']);
    $byDescription = Product::factory()->create(['name' => 'Radio Onda', 'short_description' => 'Accompagna le cuffie.']);
    Product::factory()->create(['name' => 'Lampada Faro', 'short_description' => 'Luce calda.', 'sku' => 'CDS-CUF-001']);

    expect(Product::search('cuffie')->orderBy('id')->pluck('id')->all())->toBe([$byName->id, $byDescription->id]);
});

it('treats like wildcards in the search term as plain text', function () {
    Product::factory()->create(['name' => 'Cuffie Aria']);
    $discounted = Product::factory()->create(['name' => 'Cavo Seta 100% cotone']);

    expect(Product::search('%')->pluck('id')->all())->toBe([$discounted->id]);
});

it('searches the admin catalog by name or sku', function () {
    $byName = Product::factory()->create(['name' => 'Cuffie Aria', 'sku' => 'CDS-AUD-001']);
    $bySku = Product::factory()->create(['name' => 'Radio Onda', 'sku' => 'CDS-RIC-777']);
    Product::factory()->create(['name' => 'Lampada Faro', 'sku' => 'CDS-CAS-001', 'short_description' => 'Accompagna ric.']);

    expect(Product::searchByNameOrSku('aria')->pluck('id')->all())->toBe([$byName->id])
        ->and(Product::searchByNameOrSku('RIC-777')->pluck('id')->all())->toBe([$bySku->id]);
});

it('filters by category slug', function () {
    $audio = Category::factory()->create(['name' => 'Audio']);
    $inAudio = Product::factory()->for($audio)->create();
    Product::factory()->create();

    expect(Product::inCategory('audio')->pluck('id')->all())->toBe([$inAudio->id]);
});

it('filters by price range with optional bounds', function (?int $min, ?int $max, array $expectedPrices) {
    foreach ([1990, 4990, 9990] as $price) {
        Product::factory()->create(['price' => $price]);
    }

    expect(Product::priceBetween($min, $max)->orderBy('price')->pluck('price')->all())->toBe($expectedPrices);
})->with([
    'both bounds' => [2000, 9990, [4990, 9990]],
    'only min' => [4990, null, [4990, 9990]],
    'only max' => [null, 4990, [1990, 4990]],
    'no bounds' => [null, null, [1990, 4990, 9990]],
]);

it('sorts by newest with the id as tie-breaker', function () {
    $this->travelTo('2026-09-29 12:00');
    $old = Product::factory()->create(['created_at' => '2026-09-01 10:00']);
    $sameTimeFirst = Product::factory()->create(['created_at' => '2026-09-20 10:00']);
    $sameTimeSecond = Product::factory()->create(['created_at' => '2026-09-20 10:00']);

    expect(Product::sortBy('newest')->pluck('id')->all())
        ->toBe([$sameTimeSecond->id, $sameTimeFirst->id, $old->id]);
});

it('sorts by price with the id as tie-breaker', function (string $sort, array $expectedOrder) {
    $products = [
        'a' => Product::factory()->create(['price' => 4990]),
        'b' => Product::factory()->create(['price' => 1990]),
        'c' => Product::factory()->create(['price' => 4990]),
    ];

    $ids = Product::sortBy($sort)->pluck('id')->all();

    expect($ids)->toBe(array_map(fn (string $key) => $products[$key]->id, $expectedOrder));
})->with([
    'price ascending' => ['price_asc', ['b', 'a', 'c']],
    'price descending' => ['price_desc', ['a', 'c', 'b']],
]);

it('rejects an unknown sort', function () {
    Product::sortBy('popular')->get();
})->throws(InvalidArgumentException::class);

it('keeps the active products at or below five pieces as low stock', function (int $stock, bool $active, bool $trashed, bool $included) {
    $factory = Product::factory()->state(['stock' => $stock, 'is_active' => $active]);
    $product = ($trashed ? $factory->trashed() : $factory)->create();

    expect(Product::lowStock()->pluck('id')->contains($product->id))->toBe($included);
})->with([
    'out of stock' => [0, true, false, true],
    'five pieces' => [5, true, false, true],
    'six pieces' => [6, true, false, false],
    'inactive' => [2, false, false, false],
    'soft deleted' => [2, true, true, false],
]);
