<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;

dataset('admin routes', [
    'settings' => ['GET', '/api/v1/admin/settings'],
    'product list' => ['GET', '/api/v1/admin/products'],
    'product creation' => ['POST', '/api/v1/admin/products'],
    'product detail' => ['GET', '/api/v1/admin/products/{product}'],
    'product update' => ['PATCH', '/api/v1/admin/products/{product}'],
    'product deletion' => ['DELETE', '/api/v1/admin/products/{product}'],
    'image upload' => ['POST', '/api/v1/admin/products/{product}/images'],
    'image deletion' => ['DELETE', '/api/v1/admin/products/{product}/images/{image}'],
    'category list' => ['GET', '/api/v1/admin/categories'],
    'category creation' => ['POST', '/api/v1/admin/categories'],
    'category update' => ['PATCH', '/api/v1/admin/categories/{category}'],
    'category deletion' => ['DELETE', '/api/v1/admin/categories/{category}'],
]);

function adminRouteUri(string $uri): string
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $image = ProductImage::factory()->for($product)->create();

    return str_replace(
        ['{product}', '{image}', '{category}'],
        [$product->id, $image->id, Category::factory()->create()->id],
        $uri,
    );
}

it('answers 401 to a guest', function (string $method, string $uri) {
    $this->json($method, adminRouteUri($uri))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Devi accedere per continuare.']);
})->with('admin routes');

it('answers 403 to a customer', function (string $method, string $uri) {
    actingAsVerifiedCustomer();

    $this->json($method, adminRouteUri($uri))
        ->assertForbidden()
        ->assertExactJson(['message' => 'Non hai i permessi per questa operazione.']);
})->with('admin routes');

it('leaves products and categories untouched when a customer calls the admin routes', function () {
    actingAsVerifiedCustomer();
    $category = Category::factory()->create(['name' => 'Audio']);
    $product = Product::factory()->for($category)->create(['name' => 'Cuffie Aria']);

    $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertForbidden();
    $this->deleteJson("/api/v1/admin/categories/{$category->id}")->assertForbidden();
    $this->patchJson("/api/v1/admin/categories/{$category->id}", ['name' => 'Altro'])->assertForbidden();

    $this->assertNotSoftDeleted($product);
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Audio']);
});
