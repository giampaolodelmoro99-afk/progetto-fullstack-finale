<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    actingAsAdmin();
});

it('lists every category by id with the product counts', function () {
    $audio = Category::factory()->create(['name' => 'Audio', 'description' => 'Cuffie, auricolari, diffusori']);
    $desk = Category::factory()->create(['name' => 'Scrivania']);
    $travel = Category::factory()->create(['name' => 'Viaggio']);
    Product::factory()->for($audio)->create();
    Product::factory()->for($audio)->inactive()->create();
    Product::factory()->for($audio)->trashed()->create();
    Product::factory()->for($desk)->trashed()->create();

    $this->getJson('/api/v1/admin/categories')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['id' => $audio->id, 'name' => 'Audio', 'slug' => 'audio', 'description' => 'Cuffie, auricolari, diffusori', 'products_count' => 2, 'can_delete' => false],
                ['id' => $desk->id, 'name' => 'Scrivania', 'slug' => 'scrivania', 'description' => $desk->description, 'products_count' => 0, 'can_delete' => false],
                ['id' => $travel->id, 'name' => 'Viaggio', 'slug' => 'viaggio', 'description' => $travel->description, 'products_count' => 0, 'can_delete' => true],
            ],
        ]);
});

it('lists the categories with the same number of queries for 2 and for 8 categories', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/admin/categories')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    Category::factory()->count(2)->has(Product::factory()->count(2))->create();
    $few = $count();
    Category::factory()->count(6)->has(Product::factory()->count(2))->create();

    expect($count())->toBe($few);
});

it('creates a category with the slug derived from the name, ignoring a slug sent', function () {
    $response = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Casa smart',
        'description' => 'Luci e clima',
        'slug' => 'scelto-dal-client',
    ])->assertCreated();

    $category = Category::sole();
    $response->assertExactJson([
        'data' => ['id' => $category->id, 'name' => 'Casa smart', 'slug' => 'casa-smart', 'description' => 'Luci e clima', 'products_count' => 0, 'can_delete' => true],
    ]);
});

it('keeps the slug when the name changes or a slug is sent', function () {
    $category = Category::factory()->create(['name' => 'Casa smart']);
    Product::factory()->for($category)->create();

    $this->patchJson("/api/v1/admin/categories/{$category->id}", [
        'name' => 'Domotica',
        'description' => null,
        'slug' => 'domotica',
    ])
        ->assertOk()
        ->assertExactJson([
            'data' => ['id' => $category->id, 'name' => 'Domotica', 'slug' => 'casa-smart', 'description' => null, 'products_count' => 1, 'can_delete' => false],
        ]);

    $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Domotica', 'slug' => 'casa-smart']);
});

it('accepts a rename that changes only the case', function () {
    $category = Category::factory()->create(['name' => 'Casa smart']);

    $this->patchJson("/api/v1/admin/categories/{$category->id}", ['name' => 'Casa Smart'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Casa Smart')
        ->assertJsonPath('data.slug', 'casa-smart');
});

it('rejects invalid categories', function (string $method, array $body, string $field) {
    Category::factory()->create(['name' => 'Casa smart']);
    Category::factory()->create(['name' => 'Audio']);
    $edited = Category::factory()->create(['name' => 'Viaggio']);

    $uri = $method === 'POST' ? '/api/v1/admin/categories' : "/api/v1/admin/categories/{$edited->id}";

    $this->json($method, $uri, $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing name on creation' => ['POST', ['name' => ''], 'name'],
    'duplicate name on creation' => ['POST', ['name' => 'Audio'], 'name'],
    'name with the slug of another category on creation' => ['POST', ['name' => 'Casa-Smart'], 'name'],
    'name of 51 characters' => ['POST', ['name' => str_repeat('a', 51)], 'name'],
    'description of 256 characters' => ['POST', ['name' => 'Ufficio', 'description' => str_repeat('a', 256)], 'description'],
    'duplicate name on update' => ['PATCH', ['name' => 'Audio'], 'name'],
    'name with the slug of another category on update' => ['PATCH', ['name' => 'casa smart!'], 'name'],
    'missing name on update' => ['PATCH', ['description' => 'Solo descrizione'], 'name'],
]);

it('answers in italian for a name already used, also through the slug', function (string $name) {
    Category::factory()->create(['name' => 'Casa smart']);

    $this->postJson('/api/v1/admin/categories', ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name', ['Esiste già una categoria con questo nome']);
})->with([
    'same name' => ['Casa smart'],
    'same slug' => ['Casa - Smart'],
]);

it('accepts a name of 50 characters and a description of 255', function () {
    $this->postJson('/api/v1/admin/categories', ['name' => str_repeat('a', 50), 'description' => str_repeat('b', 255)])->assertCreated();
});

it('deletes a category without products', function () {
    $category = Category::factory()->create(['name' => 'Viaggio']);

    $this->deleteJson("/api/v1/admin/categories/{$category->id}")->assertNoContent();

    $this->assertModelMissing($category);
});

it('refuses to delete a category with products', function (string $state, string $message) {
    $category = Category::factory()->create(['name' => 'Audio']);
    $factory = Product::factory()->for($category);
    match ($state) {
        'active' => $factory->create(),
        'inactive' => $factory->inactive()->create(),
        'trashed' => $factory->trashed()->create(),
    };

    $this->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertConflict()
        ->assertExactJson(['message' => $message, 'code' => 'category_has_products']);

    $this->assertModelExists($category);
})->with([
    'an active product' => ['active', 'Contiene prodotti: spostali prima di eliminarla.'],
    'a deactivated product' => ['inactive', 'Contiene prodotti: spostali prima di eliminarla.'],
    'only a deleted product' => ['trashed', 'È collegata a prodotti eliminati: non si può eliminare.'],
]);

it('answers 404 for a missing category', function () {
    $this->patchJson('/api/v1/admin/categories/999999', ['name' => 'Altro'])->assertNotFound();
    $this->deleteJson('/api/v1/admin/categories/999999')->assertNotFound();
});

it('lists the new category in the public menu, last, once it has an active product', function () {
    $audio = Category::factory()->create(['name' => 'Audio']);
    Product::factory()->for($audio)->create();

    $id = $this->postJson('/api/v1/admin/categories', ['name' => 'Viaggio'])->assertCreated()->json('data.id');

    expect(collect($this->getJson('/api/v1/categories')->json('data'))->pluck('slug')->all())->toBe(['audio']);

    Product::factory()->for(Category::findOrFail($id))->create();

    expect(collect($this->getJson('/api/v1/categories')->json('data'))->pluck('slug')->all())->toBe(['audio', 'viaggio']);
});
