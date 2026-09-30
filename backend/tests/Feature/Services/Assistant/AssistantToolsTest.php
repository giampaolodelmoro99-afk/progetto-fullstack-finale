<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\Assistant\AssistantTools;

function searchProductsToolSlugs(array $arguments): array
{
    return array_column(app(AssistantTools::class)->call('search_products', $arguments)['products'], 'slug');
}

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');

    $audio = Category::factory()->create(['name' => 'Audio']);
    $charging = Category::factory()->create(['name' => 'Ricarica ed energia']);

    Product::factory()->for($audio)->older()->create(['name' => 'Cuffie Aria', 'price' => 12900, 'stock' => 20]);
    Product::factory()->for($audio)->newArrival()->create(['name' => 'Cuffie Aria Studio', 'price' => 24900, 'stock' => 2]);
    Product::factory()->for($audio)->outOfStock()->older()->create(['name' => 'Diffusore Eco', 'price' => 8900]);
    Product::factory()->for($charging)->older()->create(['name' => 'Caricatore Brezza 65 W', 'price' => 4990]);
    Product::factory()->for($audio)->inactive()->create(['name' => 'Cuffie Nascoste', 'price' => 9900]);
    Product::factory()->for($audio)->trashed()->create(['name' => 'Cuffie Eliminate', 'price' => 9900]);
});

it('searches only active products that are not deleted', function (array $arguments, array $expected) {
    expect(searchProductsToolSlugs($arguments))->toEqualCanonicalizing($expected);
})->with([
    'no filters' => [[], ['cuffie-aria', 'cuffie-aria-studio', 'diffusore-eco', 'caricatore-brezza-65-w']],
    'search' => [['search' => 'cuffie'], ['cuffie-aria', 'cuffie-aria-studio']],
    'category' => [['category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio', 'diffusore-eco']],
    'max price in euro' => [['max_price' => 129], ['cuffie-aria', 'diffusore-eco', 'caricatore-brezza-65-w']],
    'min price with decimals' => [['min_price' => 49.9, 'max_price' => 49.9], ['caricatore-brezza-65-w']],
    'price as a string' => [['max_price' => '89'], ['diffusore-eco', 'caricatore-brezza-65-w']],
    'in stock' => [['in_stock' => true, 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio']],
    'in stock as a string' => [['in_stock' => 'true', 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio']],
    'new arrivals' => [['new' => true], ['cuffie-aria-studio']],
]);

it('drops invalid arguments and keeps the valid ones', function (array $arguments, array $expected) {
    expect(searchProductsToolSlugs($arguments))->toEqualCanonicalizing($expected);
})->with([
    'unknown category' => [['category' => 'cucina', 'search' => 'cuffie'], ['cuffie-aria', 'cuffie-aria-studio']],
    'negative price' => [['max_price' => -5, 'category' => 'ricarica-ed-energia'], ['caricatore-brezza-65-w']],
    'price over the maximum' => [['min_price' => 10000, 'category' => 'ricarica-ed-energia'], ['caricatore-brezza-65-w']],
    'price as text' => [['max_price' => 'poco', 'category' => 'ricarica-ed-energia'], ['caricatore-brezza-65-w']],
    'unknown sort' => [['sort' => 'popular', 'category' => 'ricarica-ed-energia'], ['caricatore-brezza-65-w']],
    'boolean as text' => [['in_stock' => 'forse', 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio', 'diffusore-eco']],
    'search as an array' => [['search' => ['cuffie'], 'category' => 'ricarica-ed-energia'], ['caricatore-brezza-65-w']],
    'unknown argument' => [['sql' => 'drop table products', 'category' => 'ricarica-ed-energia'], ['caricatore-brezza-65-w']],
]);

it('sorts with the requested order', function () {
    expect(searchProductsToolSlugs(['sort' => 'price_desc']))
        ->toBe(['cuffie-aria-studio', 'cuffie-aria', 'diffusore-eco', 'caricatore-brezza-65-w']);
});

it('returns at most the configured number of products', function () {
    Product::factory()->count(10)->create();

    expect(searchProductsToolSlugs([]))->toHaveCount(8);
});

it('returns a compact summary with formatted price and availability', function () {
    $result = app(AssistantTools::class)->call('search_products', ['search' => 'Cuffie Aria Studio']);

    expect($result['products'])->toHaveCount(1)
        ->and($result['products'][0])->toMatchArray([
            'name' => 'Cuffie Aria Studio',
            'slug' => 'cuffie-aria-studio',
            'category' => 'Audio',
            'price' => '249,00 €',
            'availability' => 'Ultimi 2 pezzi',
        ])
        ->and($result['products'][0])->toHaveKey('short_description');
});

it('returns the product detail with the specifications', function () {
    Product::query()->where('slug', 'cuffie-aria')->first()->update([
        'specifications' => [['label' => 'Autonomia', 'value' => '30 ore'], ['label' => 'Peso', 'value' => '250 g']],
    ]);

    $result = app(AssistantTools::class)->call('get_product', ['slug' => 'cuffie-aria']);

    expect($result['product'])->toMatchArray([
        'name' => 'Cuffie Aria',
        'price' => '129,00 €',
        'availability' => 'Disponibile',
        'specifications' => ['Autonomia: 30 ore', 'Peso: 250 g'],
    ]);
});

it('does not return inactive, deleted or missing products', function (mixed $slug) {
    expect(app(AssistantTools::class)->call('get_product', ['slug' => $slug]))
        ->toBe(['error' => 'Prodotto non trovato.']);
})->with(['cuffie-nascoste', 'cuffie-eliminate', 'cuffie-inesistenti', '', null, [['cuffie-aria']]]);

it('answers an unknown tool with an error result', function () {
    expect(app(AssistantTools::class)->call('delete_products', []))
        ->toBe(['error' => 'Strumento non disponibile.']);
});

it('declares the catalog and shop tools', function () {
    $names = array_map(fn (array $tool) => $tool['function']['name'], app(AssistantTools::class)->definitions());

    expect($names)->toBe(['search_products', 'get_product', 'get_shop_info']);
});

it('offers the category slugs from the database as an enum', function () {
    Category::factory()->create(['name' => 'Casa smart']);

    $search = collect(app(AssistantTools::class)->definitions())->firstWhere('function.name', 'search_products');

    expect($search['function']['parameters']['properties']['category']['enum'])
        ->toBe(['audio', 'ricarica-ed-energia', 'casa-smart']);
});

it('leaves the category free when there are no categories', function () {
    Product::query()->forceDelete();
    Category::query()->delete();

    $search = collect(app(AssistantTools::class)->definitions())->firstWhere('function.name', 'search_products');

    expect($search['function']['parameters']['properties']['category'])->not->toHaveKey('enum');
});

it('offers the support sections as an enum', function () {
    $shopInfo = collect(app(AssistantTools::class)->definitions())->firstWhere('function.name', 'get_shop_info');

    expect($shopInfo['function']['parameters']['properties']['section']['enum'])
        ->toBe(['spedizioni', 'resi', 'garanzia', 'contatti']);
});

it('returns one support section with the configured amounts', function () {
    config(['shop.shipping_cost' => 1250, 'shop.free_shipping_threshold' => 124900]);

    expect(app(AssistantTools::class)->call('get_shop_info', ['section' => 'spedizioni']))->toBe([
        'sections' => [[
            'title' => 'Spedizioni e consegne',
            'paragraphs' => [
                'Spediamo in tutta Italia, con consegna in 24/48 ore.',
                'La spedizione costa 12,50 € ed è gratuita per gli ordini da 1.249,00 € in su.',
            ],
        ]],
    ]);
});

it('returns every support section when the section is missing or not valid', function (array $arguments) {
    $titles = array_column(app(AssistantTools::class)->call('get_shop_info', $arguments)['sections'], 'title');

    expect($titles)->toBe(['Spedizioni e consegne', 'Resi e rimborsi', 'Garanzia', 'Contatti']);
})->with([
    'missing' => [[]],
    'unknown' => [['section' => 'pagamenti']],
    'not a string' => [['section' => ['resi']]],
]);
