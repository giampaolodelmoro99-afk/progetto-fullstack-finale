<?php

use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
    actingAsAdmin();
});

it('creates a product with the slug derived from the name', function () {
    $category = Category::factory()->create(['name' => 'Audio']);

    $response = $this->postJson('/api/v1/admin/products', adminProductPayload([
        'category_id' => $category->id,
        'specifications' => [
            ['label' => 'Peso', 'value' => '250 g'],
            ['label' => 'Autonomia', 'value' => '30 ore'],
            ['label' => 'Connessione', 'value' => 'Bluetooth 5.3'],
        ],
    ]))->assertCreated();

    $product = Product::sole();
    $response->assertExactJson([
        'data' => [
            'id' => $product->id,
            'name' => 'Cuffie Aria',
            'slug' => 'cuffie-aria',
            'sku' => 'CDS-AUD-001',
            'category' => ['id' => $category->id, 'name' => 'Audio', 'slug' => 'audio'],
            'short_description' => 'Cuffie wireless con cancellazione del rumore.',
            'description' => 'Suono pulito e bilanciato.',
            'specifications' => [
                ['label' => 'Peso', 'value' => '250 g'],
                ['label' => 'Autonomia', 'value' => '30 ore'],
                ['label' => 'Connessione', 'value' => 'Bluetooth 5.3'],
            ],
            'price' => 34900,
            'stock' => 18,
            'stock_status' => ['status' => 'available', 'label' => 'Disponibile'],
            'is_active' => true,
            'cover' => null,
            'images' => [],
            'updated_at' => '2026-09-15T12:00:00+02:00',
        ],
    ]);
    expect($product->specifications)->toBe([
        ['label' => 'Peso', 'value' => '250 g'],
        ['label' => 'Autonomia', 'value' => '30 ore'],
        ['label' => 'Connessione', 'value' => 'Bluetooth 5.3'],
    ]);
});

it('ignores a slug sent by the client and adds a suffix to a repeated name', function () {
    Product::factory()->for(Category::factory())->trashed()->create(['name' => 'Cuffie Aria', 'sku' => 'CDS-AUD-099']);

    $this->postJson('/api/v1/admin/products', adminProductPayload(['slug' => 'scelto-dal-client']))
        ->assertCreated()
        ->assertJsonPath('data.slug', 'cuffie-aria-2');
});

it('accepts an empty technical sheet, no description and the boundary values', function () {
    $this->postJson('/api/v1/admin/products', adminProductPayload([
        'description' => null,
        'specifications' => [],
        'short_description' => str_repeat('a', 160),
        'price' => 999999,
        'stock' => 999999,
    ]))->assertCreated();

    $this->postJson('/api/v1/admin/products', adminProductPayload([
        'sku' => 'CDS-AUD-002',
        'price' => 1,
        'stock' => 0,
        'is_active' => false,
    ]))->assertCreated()->assertJsonPath('data.is_active', false);
});

it('rejects invalid data', function (array $overrides, string $field) {
    $this->postJson('/api/v1/admin/products', adminProductPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Product::count())->toBe(0);
})->with([
    'lowercase sku' => [['sku' => 'cds-aud-001'], 'sku'],
    'sku with spaces' => [['sku' => 'CDS AUD 001'], 'sku'],
    'sku with underscore' => [['sku' => 'CDS_AUD'], 'sku'],
    'sku of 65 characters' => [['sku' => str_repeat('A', 65)], 'sku'],
    'missing short description' => [['short_description' => null], 'short_description'],
    'short description of 161 characters' => [['short_description' => str_repeat('a', 161)], 'short_description'],
    'description of 5001 characters' => [['description' => str_repeat('a', 5001)], 'description'],
    'price 0' => [['price' => 0], 'price'],
    'decimal price' => [['price' => 12.5], 'price'],
    'price 1000000' => [['price' => 1000000], 'price'],
    'stock -1' => [['stock' => -1], 'stock'],
    'stock 1000000' => [['stock' => 1000000], 'stock'],
    'missing category' => [['category_id' => null], 'category_id'],
    'unknown category' => [['category_id' => 999999], 'category_id'],
    'missing name' => [['name' => ''], 'name'],
    'missing is_active' => [['is_active' => null], 'is_active'],
    'missing technical sheet' => [['specifications' => null], 'specifications'],
    'technical sheet as an object' => [['specifications' => ['peso' => ['label' => 'Peso', 'value' => '250 g']]], 'specifications'],
    '21 rows of technical sheet' => [['specifications' => array_fill(0, 21, ['label' => 'Peso', 'value' => '250 g'])], 'specifications'],
    'row without value' => [['specifications' => [['label' => 'Peso']]], 'specifications.0.value'],
    'row with an extra key' => [['specifications' => [['label' => 'Peso', 'value' => '250 g', 'unit' => 'g']]], 'specifications.0'],
    'label of 65 characters' => [['specifications' => [['label' => str_repeat('a', 65), 'value' => '250 g']]], 'specifications.0.label'],
]);

it('rejects an sku already used by an active or a deleted product, with the italian message', function (bool $deleted) {
    $existing = Product::factory()->for(Category::factory())->state(['sku' => 'CDS-AUD-001']);
    ($deleted ? $existing->trashed() : $existing)->create();

    $this->postJson('/api/v1/admin/products', adminProductPayload(['sku' => 'CDS-AUD-001']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.sku.0', 'Questo SKU è già in uso');
})->with([
    'active' => [false],
    'deleted' => [true],
]);

it('explains the sku format in italian', function () {
    $this->postJson('/api/v1/admin/products', adminProductPayload(['sku' => 'cds-aud-001']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.sku.0', 'Usa solo lettere maiuscole, numeri e trattini');
});
