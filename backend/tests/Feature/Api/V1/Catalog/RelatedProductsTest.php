<?php

use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
    $this->audio = Category::factory()->create(['name' => 'Audio']);
    $this->product = Product::factory()->for($this->audio)->create(['name' => 'Cuffie Aria', 'created_at' => '2026-09-14 10:00']);
});

it('returns up to four products of the same category, newest first', function () {
    Product::factory()->for($this->audio)->create(['name' => 'Diffusore Eco', 'created_at' => '2026-09-01 10:00']);
    Product::factory()->for($this->audio)->create(['name' => 'Diffusore Eco Mini', 'created_at' => '2026-09-10 10:00']);
    Product::factory()->for($this->audio)->create(['name' => 'Radio Onda', 'created_at' => '2026-07-01 10:00']);
    Product::factory()->for($this->audio)->create(['name' => 'Radio Onda Portatile', 'created_at' => '2026-08-01 10:00']);
    Product::factory()->for($this->audio)->create(['name' => 'Auricolari Sussurro', 'created_at' => '2026-06-01 10:00']);
    Product::factory()->for($this->audio)->create(['name' => 'Auricolari Sussurro Sport', 'created_at' => '2026-05-01 10:00']);

    $this->getJson('/api/v1/products/cuffie-aria/related')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'price', 'is_new', 'availability', 'category', 'cover']]])
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('data.0.description');

    expect(responseSlugs($this->getJson('/api/v1/products/cuffie-aria/related')))
        ->toBe(['diffusore-eco-mini', 'diffusore-eco', 'radio-onda-portatile', 'radio-onda']);
});

it('leaves out the current, out of stock, deactivated, deleted and other category products', function () {
    $charging = Category::factory()->create(['name' => 'Ricarica ed energia']);

    Product::factory()->for($this->audio)->create(['name' => 'Diffusore Eco']);
    Product::factory()->for($this->audio)->outOfStock()->create(['name' => 'Radio Onda']);
    Product::factory()->for($this->audio)->inactive()->create(['name' => 'Cuffie Nascoste']);
    Product::factory()->for($this->audio)->trashed()->create(['name' => 'Cuffie Eliminate']);
    Product::factory()->for($charging)->create(['name' => 'Caricatore Brezza 65 W']);

    expect(responseSlugs($this->getJson('/api/v1/products/cuffie-aria/related')->assertOk()))
        ->toBe(['diffusore-eco']);
});

it('returns an empty list when the category has no other products', function () {
    $this->getJson('/api/v1/products/cuffie-aria/related')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('answers 404 for a deactivated, a deleted or an unknown product', function (string $slug) {
    Product::factory()->for($this->audio)->inactive()->create(['name' => 'Cuffie Nascoste']);
    Product::factory()->for($this->audio)->trashed()->create(['name' => 'Cuffie Eliminate']);
    Product::factory()->for($this->audio)->create(['name' => 'Diffusore Eco']);

    $this->getJson('/api/v1/products/'.$slug.'/related')->assertNotFound();
})->with(['cuffie-nascoste', 'cuffie-eliminate', 'cuffie-inesistenti']);
