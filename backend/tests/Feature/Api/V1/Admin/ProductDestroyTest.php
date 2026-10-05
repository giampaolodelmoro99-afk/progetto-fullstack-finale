<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    actingAsAdmin();
});

it('soft deletes a product and keeps its images', function () {
    $product = Product::factory()->for(Category::factory())->create(['name' => 'Cuffie Aria', 'sku' => 'CDS-AUD-001']);
    Storage::disk('public')->put('products/cuffie.webp', 'immagine');
    $image = ProductImage::factory()->for($product)->create(['path' => 'products/cuffie.webp']);

    $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertNoContent();

    $this->assertSoftDeleted($product);
    $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    Storage::disk('public')->assertExists('products/cuffie.webp');
    expect($this->getJson('/api/v1/admin/products')->json('data'))->toBe([]);
    $this->getJson('/api/v1/products')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/products/cuffie-aria')->assertNotFound();
});

it('answers 404 for a product already deleted', function () {
    $product = Product::factory()->for(Category::factory())->trashed()->create();

    $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertNotFound();
});
