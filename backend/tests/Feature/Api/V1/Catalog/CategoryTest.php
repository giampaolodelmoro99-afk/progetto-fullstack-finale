<?php

use App\Models\Category;
use App\Models\Product;

it('lists every category by id, empty ones included', function () {
    $audio = Category::factory()->create(['name' => 'Audio', 'description' => 'Cuffie, auricolari, diffusori']);
    $travel = Category::factory()->create(['name' => 'Viaggio', 'description' => 'Custodie e adattatori per chi viaggia']);
    $desk = Category::factory()->create(['name' => 'Scrivania', 'description' => null]);
    Product::factory()->for($audio)->create();

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['id' => $audio->id, 'name' => 'Audio', 'slug' => 'audio', 'description' => 'Cuffie, auricolari, diffusori'],
                ['id' => $travel->id, 'name' => 'Viaggio', 'slug' => 'viaggio', 'description' => 'Custodie e adattatori per chi viaggia'],
                ['id' => $desk->id, 'name' => 'Scrivania', 'slug' => 'scrivania', 'description' => null],
            ],
        ]);
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
