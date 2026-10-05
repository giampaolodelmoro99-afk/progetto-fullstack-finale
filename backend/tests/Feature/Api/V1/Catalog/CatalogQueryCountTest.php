<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;

function catalogQueryCount(string $uri): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->getJson($uri)->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function productsWithImages(Category $category, int $count): void
{
    Product::factory()->count($count)->for($category)->create()->each(function (Product $product) {
        ProductImage::factory()->count(2)->for($product)->create();
    });
}

it('lists the products with the same number of queries for 2 and for 10 products', function () {
    $audio = Category::factory()->create(['name' => 'Audio']);
    productsWithImages($audio, 2);
    $few = catalogQueryCount('/api/v1/products?category=audio');

    productsWithImages(Category::factory()->create(['name' => 'Scrivania']), 10);
    $many = catalogQueryCount('/api/v1/products?category=scrivania');

    expect($many)->toBe($few);
});

it('loads the related products with the same number of queries for 1 and for 4 products', function () {
    $audio = Category::factory()->create(['name' => 'Audio']);
    $first = Product::factory()->for($audio)->create();
    productsWithImages($audio, 1);
    $few = catalogQueryCount('/api/v1/products/'.$first->slug.'/related');

    $desk = Category::factory()->create(['name' => 'Scrivania']);
    $second = Product::factory()->for($desk)->create();
    productsWithImages($desk, 4);
    $many = catalogQueryCount('/api/v1/products/'.$second->slug.'/related');

    expect($many)->toBe($few);
});

it('shows a product with a fixed number of queries, whatever the number of images', function () {
    $category = Category::factory()->create();
    $few = Product::factory()->for($category)->create();
    ProductImage::factory()->for($few)->create();
    $many = Product::factory()->for($category)->create();
    ProductImage::factory()->count(6)->for($many)->create();

    expect(catalogQueryCount('/api/v1/products/'.$many->slug))
        ->toBe(catalogQueryCount('/api/v1/products/'.$few->slug));
});
