<?php

use App\Models\Category;
use App\Models\Product;

it('lists by id only the categories with an active product', function () {
    $audio = Category::factory()->create(['name' => 'Audio', 'description' => 'Cuffie, auricolari, diffusori']);
    Category::factory()->create(['name' => 'Viaggio', 'description' => 'Custodie e adattatori per chi viaggia']);
    $desk = Category::factory()->create(['name' => 'Scrivania', 'description' => null]);
    $wearables = Category::factory()->create(['name' => 'Indossabili', 'description' => 'Orologi e cinturini']);
    $smartHome = Category::factory()->create(['name' => 'Casa smart', 'description' => 'Luci e clima']);
    Product::factory()->for($audio)->create();
    Product::factory()->for($desk)->create();
    Product::factory()->for($desk)->inactive()->create();
    Product::factory()->count(2)->for($wearables)->inactive()->create();
    Product::factory()->for($smartHome)->create()->delete();

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['id' => $audio->id, 'name' => 'Audio', 'slug' => 'audio', 'description' => 'Cuffie, auricolari, diffusori'],
                ['id' => $desk->id, 'name' => 'Scrivania', 'slug' => 'scrivania', 'description' => null],
            ],
        ]);
});

it('keeps every category in the admin list', function () {
    Category::factory()->create(['name' => 'Viaggio']);
    $wearables = Category::factory()->create(['name' => 'Indossabili']);
    Product::factory()->for($wearables)->inactive()->create();

    actingAsAdmin();

    $this->getJson('/api/v1/admin/categories')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'viaggio')
        ->assertJsonPath('data.1.slug', 'indossabili');
});

it('shows a category by slug', function () {
    $category = Category::factory()->create(['name' => 'Casa smart', 'description' => 'Luci e clima']);

    $this->getJson('/api/v1/categories/casa-smart')
        ->assertOk()
        ->assertExactJson([
            'data' => ['id' => $category->id, 'name' => 'Casa smart', 'slug' => 'casa-smart', 'description' => 'Luci e clima'],
        ]);
});

it('answers 404 for an unknown category', function () {
    $this->getJson('/api/v1/categories/inesistente')
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);
});
