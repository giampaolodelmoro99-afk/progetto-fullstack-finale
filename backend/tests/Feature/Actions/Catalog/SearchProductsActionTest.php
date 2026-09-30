<?php

use App\Actions\Catalog\SearchProductsAction;
use App\Models\Category;
use App\Models\Product;

function searchProductsActionSlugs(array $filters): array
{
    return app(SearchProductsAction::class)->execute($filters)->pluck('slug')->all();
}

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');

    $audio = Category::factory()->create(['name' => 'Audio']);
    $charging = Category::factory()->create(['name' => 'Ricarica ed energia']);

    Product::factory()->for($audio)->older()->create(['name' => 'Cuffie Aria', 'price' => 12900]);
    Product::factory()->for($audio)->newArrival()->create(['name' => 'Cuffie Aria Studio', 'price' => 24900]);
    Product::factory()->for($audio)->outOfStock()->older()->create(['name' => 'Diffusore Eco', 'price' => 8900]);
    Product::factory()->for($charging)->older()->create(['name' => 'Caricatore Brezza 65 W', 'price' => 4900]);
    Product::factory()->for($audio)->inactive()->create(['name' => 'Cuffie Nascoste', 'price' => 9900]);
    Product::factory()->for($audio)->trashed()->create(['name' => 'Cuffie Eliminate', 'price' => 9900]);
});

it('returns only active products that are not deleted', function () {
    expect(searchProductsActionSlugs([]))->toHaveCount(4)
        ->not->toContain('cuffie-nascoste')
        ->not->toContain('cuffie-eliminate');
});

it('applies the catalog filters', function (array $filters, array $expected) {
    expect(searchProductsActionSlugs($filters))->toEqualCanonicalizing($expected);
})->with([
    'search' => [['search' => 'cuffie'], ['cuffie-aria', 'cuffie-aria-studio']],
    'category' => [['category' => 'ricarica-ed-energia'], ['caricatore-brezza-65-w']],
    'min price in cents' => [['min_price' => 12900], ['cuffie-aria', 'cuffie-aria-studio']],
    'max price in cents' => [['max_price' => 8900], ['diffusore-eco', 'caricatore-brezza-65-w']],
    'in stock' => [['in_stock' => true, 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio']],
    'new arrivals' => [['new' => true], ['cuffie-aria-studio']],
]);

it('sorts by price', function () {
    expect(searchProductsActionSlugs(['sort' => 'price_asc']))
        ->toBe(['caricatore-brezza-65-w', 'diffusore-eco', 'cuffie-aria', 'cuffie-aria-studio']);
});

it('sorts by newest by default', function () {
    expect(searchProductsActionSlugs([])[0])->toBe('cuffie-aria-studio');
});
