<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;

function buildCatalog(): void
{
    $audio = Category::factory()->create(['name' => 'Audio']);
    $charging = Category::factory()->create(['name' => 'Ricarica ed energia']);

    Product::factory()->for($audio)->create(['name' => 'Cuffie Aria', 'price' => 12900, 'created_at' => '2026-07-01 10:00']);
    Product::factory()->for($audio)->create(['name' => 'Cuffie Aria Studio', 'price' => 24900, 'created_at' => '2026-09-10 10:00']);
    Product::factory()->for($audio)->outOfStock()->create(['name' => 'Diffusore Eco', 'price' => 8900, 'created_at' => '2026-06-01 10:00']);
    Product::factory()->for($charging)->create(['name' => 'Caricatore Brezza 65 W', 'price' => 4900, 'created_at' => '2026-05-01 10:00']);
    Product::factory()->for($charging)->create(['name' => 'Cavo Seta USB-C 1 m', 'price' => 1900, 'short_description' => 'Un cavo intrecciato per ricaricare le cuffie.', 'created_at' => '2026-09-01 10:00']);
    Product::factory()->for($audio)->inactive()->create(['name' => 'Cuffie Nascoste', 'price' => 900, 'created_at' => '2026-09-12 10:00']);
    Product::factory()->for($audio)->trashed()->create(['name' => 'Cuffie Eliminate', 'price' => 99900, 'created_at' => '2026-09-13 10:00']);
}

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
});

it('lists the active products for a guest with the pagination and the price range', function () {
    buildCatalog();

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'price', 'is_new', 'availability' => ['status', 'label'], 'category' => ['name', 'slug'], 'cover']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'price_range' => ['min', 'max']],
        ])
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 12)
        ->assertJsonPath('meta.price_range', ['min' => 1900, 'max' => 24900]);
});

it('leaves out deactivated and deleted products', function () {
    buildCatalog();

    expect(responseSlugs($this->getJson('/api/v1/products')))
        ->not->toContain('cuffie-nascoste')
        ->not->toContain('cuffie-eliminate');
});

it('exposes only the public fields of a product', function () {
    $product = Product::factory()->for(Category::factory()->create(['name' => 'Audio']))->create([
        'name' => 'Diffusore Eco',
        'price' => 124900,
        'stock' => 2,
        'created_at' => '2026-06-01 10:00',
    ]);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertExactJson([
            'data' => [[
                'id' => $product->id,
                'name' => 'Diffusore Eco',
                'slug' => 'diffusore-eco',
                'price' => 124900,
                'is_new' => false,
                'availability' => ['status' => 'low_stock', 'label' => 'Ultimi 2 pezzi'],
                'category' => ['name' => 'Audio', 'slug' => 'audio'],
                'cover' => null,
            ]],
            'links' => [
                'first' => 'http://localhost:8000/api/v1/products?page=1',
                'last' => 'http://localhost:8000/api/v1/products?page=1',
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'links' => [
                    ['url' => null, 'label' => '&laquo; Precedente', 'page' => null, 'active' => false],
                    ['url' => 'http://localhost:8000/api/v1/products?page=1', 'label' => '1', 'page' => 1, 'active' => true],
                    ['url' => null, 'label' => 'Successiva &raquo;', 'page' => null, 'active' => false],
                ],
                'path' => 'http://localhost:8000/api/v1/products',
                'per_page' => 12,
                'to' => 1,
                'total' => 1,
                'price_range' => ['min' => 124900, 'max' => 124900],
            ],
        ]);
});

it('applies the catalog filters', function (array $query, array $expected) {
    buildCatalog();

    expect(responseSlugs($this->getJson('/api/v1/products?'.http_build_query($query))))
        ->toEqualCanonicalizing($expected);
})->with([
    'search in the name' => [['search' => 'aria'], ['cuffie-aria', 'cuffie-aria-studio']],
    'search in the short description' => [['search' => 'intrecciato'], ['cavo-seta-usb-c-1-m']],
    'category' => [['category' => 'ricarica-ed-energia'], ['caricatore-brezza-65-w', 'cavo-seta-usb-c-1-m']],
    'min price' => [['min_price' => 12900], ['cuffie-aria', 'cuffie-aria-studio']],
    'max price' => [['max_price' => 4900], ['caricatore-brezza-65-w', 'cavo-seta-usb-c-1-m']],
    'price range' => [['min_price' => 4900, 'max_price' => 12900], ['cuffie-aria', 'diffusore-eco', 'caricatore-brezza-65-w']],
    'in stock with 1' => [['in_stock' => '1', 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio']],
    'in stock with true' => [['in_stock' => 'true', 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio']],
    'in stock with TRUE' => [['in_stock' => 'TRUE', 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio']],
    'in stock with 0' => [['in_stock' => '0', 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio', 'diffusore-eco']],
    'in stock with false' => [['in_stock' => 'false', 'category' => 'audio'], ['cuffie-aria', 'cuffie-aria-studio', 'diffusore-eco']],
    'new with 1' => [['new' => '1'], ['cuffie-aria-studio', 'cavo-seta-usb-c-1-m']],
    'new with true' => [['new' => 'true'], ['cuffie-aria-studio', 'cavo-seta-usb-c-1-m']],
    'new with False' => [['new' => 'False'], ['cuffie-aria', 'cuffie-aria-studio', 'diffusore-eco', 'caricatore-brezza-65-w', 'cavo-seta-usb-c-1-m']],
    'combined' => [['category' => 'audio', 'new' => '1', 'max_price' => 30000], ['cuffie-aria-studio']],
]);

it('sorts by newest by default and by price on request', function (?string $sort, array $expected) {
    buildCatalog();

    $query = $sort === null ? '' : '?sort='.$sort;

    expect(responseSlugs($this->getJson('/api/v1/products'.$query)))->toBe($expected);
})->with([
    'default' => [null, ['cuffie-aria-studio', 'cavo-seta-usb-c-1-m', 'cuffie-aria', 'diffusore-eco', 'caricatore-brezza-65-w']],
    'newest' => ['newest', ['cuffie-aria-studio', 'cavo-seta-usb-c-1-m', 'cuffie-aria', 'diffusore-eco', 'caricatore-brezza-65-w']],
    'price ascending' => ['price_asc', ['cavo-seta-usb-c-1-m', 'caricatore-brezza-65-w', 'diffusore-eco', 'cuffie-aria', 'cuffie-aria-studio']],
    'price descending' => ['price_desc', ['cuffie-aria-studio', 'cuffie-aria', 'diffusore-eco', 'caricatore-brezza-65-w', 'cavo-seta-usb-c-1-m']],
]);

it('breaks ties by id so that the pagination is stable', function (string $sort, array $expected) {
    $category = Category::factory()->create(['name' => 'Audio']);

    foreach (['Radio Onda', 'Radio Onda Portatile', 'Radio Onda Mini'] as $name) {
        Product::factory()->for($category)->create(['name' => $name, 'price' => 5900, 'created_at' => '2026-08-01 10:00']);
    }

    expect(responseSlugs($this->getJson('/api/v1/products?sort='.$sort)))->toBe($expected);
})->with([
    'newest' => ['newest', ['radio-onda-mini', 'radio-onda-portatile', 'radio-onda']],
    'price ascending' => ['price_asc', ['radio-onda', 'radio-onda-portatile', 'radio-onda-mini']],
    'price descending' => ['price_desc', ['radio-onda', 'radio-onda-portatile', 'radio-onda-mini']],
]);

it('paginates with the allowed page sizes', function () {
    Product::factory()->count(14)->for(Category::factory())->create();

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('meta.total', 14)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/products?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2);

    $this->getJson('/api/v1/products?per_page=4')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('meta.per_page', 4)
        ->assertJsonPath('meta.last_page', 4);
});

it('accepts every allowed page size', function (int $perPage) {
    $this->getJson('/api/v1/products?per_page='.$perPage)
        ->assertOk()
        ->assertJsonPath('meta.per_page', $perPage);
})->with([4, 12, 24, 48]);

it('keeps the filters in the pagination links', function () {
    Product::factory()->count(5)->for(Category::factory()->create(['name' => 'Audio']))->create();

    $this->getJson('/api/v1/products?category=audio&per_page=4')
        ->assertOk()
        ->assertJsonPath('links.next', 'http://localhost:8000/api/v1/products?category=audio&per_page=4&page=2');
});

it('rejects invalid filters', function (array $query, string $field) {
    Category::factory()->create(['name' => 'Audio']);

    $this->getJson('/api/v1/products?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'unknown sort' => [['sort' => 'popular'], 'sort'],
    'page size not allowed' => [['per_page' => 5], 'per_page'],
    'page size too large' => [['per_page' => 100], 'per_page'],
    'page zero' => [['page' => 0], 'page'],
    'negative min price' => [['min_price' => -1], 'min_price'],
    'min price not a number' => [['min_price' => 'abc'], 'min_price'],
    'decimal max price' => [['max_price' => '12.5'], 'max_price'],
    'max price too high' => [['max_price' => 1000000], 'max_price'],
    'min price too high' => [['min_price' => 1000000], 'min_price'],
    'negative max price' => [['max_price' => -1], 'max_price'],
    'category as an array' => [['category' => ['audio']], 'category'],
    'max price below min price' => [['min_price' => 5000, 'max_price' => 4999], 'max_price'],
    'in stock not a boolean' => [['in_stock' => 'forse'], 'in_stock'],
    'new not a boolean' => [['new' => 'forse'], 'new'],
    'unknown category' => [['category' => 'inesistente'], 'category'],
    'search too long' => [['search' => str_repeat('a', 101)], 'search'],
]);

it('explains an unknown category in italian', function () {
    $this->getJson('/api/v1/products?category=inesistente')
        ->assertUnprocessable()
        ->assertJsonPath('errors.category.0', 'Questa categoria non esiste.');
});

it('accepts a max price equal to the min price and a search of 100 characters', function () {
    $this->getJson('/api/v1/products?min_price=5000&max_price=5000&search='.str_repeat('a', 100))->assertOk();
});

it('computes the price range on the category, ignoring the other filters', function (array $query, array $expected) {
    buildCatalog();

    $this->getJson('/api/v1/products?'.http_build_query($query))
        ->assertOk()
        ->assertJsonPath('meta.price_range', $expected);
})->with([
    'whole catalog, out of stock included' => [[], ['min' => 1900, 'max' => 24900]],
    'category' => [['category' => 'audio'], ['min' => 8900, 'max' => 24900]],
    'category with price filters' => [['category' => 'audio', 'min_price' => 10000, 'max_price' => 20000], ['min' => 8900, 'max' => 24900]],
    'in stock' => [['category' => 'audio', 'in_stock' => '1'], ['min' => 8900, 'max' => 24900]],
    'new arrivals' => [['new' => '1'], ['min' => 1900, 'max' => 24900]],
    'search' => [['search' => 'caricatore'], ['min' => 1900, 'max' => 24900]],
]);

it('gives an empty price range for a category without active products', function () {
    buildCatalog();
    $travel = Category::factory()->create(['name' => 'Viaggio']);
    Product::factory()->for($travel)->inactive()->create(['price' => 3900]);

    $this->getJson('/api/v1/products?category=viaggio')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.price_range', ['min' => null, 'max' => null]);
});

it('marks as new the products created within the last thirty days', function (string $createdAt, bool $isNew) {
    Product::factory()->for(Category::factory())->create(['created_at' => $createdAt]);

    $this->getJson('/api/v1/products')->assertJsonPath('data.0.is_new', $isNew);
})->with([
    '29 days ago' => ['2026-08-17 12:00', true],
    '31 days ago' => ['2026-08-15 12:00', false],
]);

it('shows the availability computed from the stock', function (int $stock, array $availability) {
    Product::factory()->for(Category::factory())->create(['stock' => $stock]);

    $this->getJson('/api/v1/products')->assertJsonPath('data.0.availability', $availability);
})->with([
    'out of stock' => [0, ['status' => 'out_of_stock', 'label' => 'Esaurito']],
    'last piece' => [1, ['status' => 'low_stock', 'label' => 'Ultimo pezzo']],
    'last two pieces' => [2, ['status' => 'low_stock', 'label' => 'Ultimi 2 pezzi']],
    'above the threshold' => [4, ['status' => 'available', 'label' => 'Disponibile']],
]);

it('uses the first image by position as cover', function () {
    $product = Product::factory()->for(Category::factory())->create(['name' => 'Cuffie Aria']);
    ProductImage::factory()->for($product)->create(['path' => 'products/cuffie-aria-retro.webp', 'alt' => 'Retro delle Cuffie Aria', 'position' => 2]);
    ProductImage::factory()->for($product)->create(['path' => 'products/cuffie-aria.webp', 'alt' => 'Cuffie Aria grafite', 'position' => 1]);

    $this->getJson('/api/v1/products')
        ->assertJsonPath('data.0.cover', [
            'url' => 'http://localhost:8000/storage/products/cuffie-aria.webp',
            'alt' => 'Cuffie Aria grafite',
        ]);
});
