<?php

use App\Models\Product;
use Illuminate\Support\Carbon;

it('computes the customer availability from the stock', function (int $stock, string $status, string $label) {
    $product = new Product(['stock' => $stock]);

    expect($product->availability)->toBe(['status' => $status, 'label' => $label]);
})->with([
    [0, 'out_of_stock', 'Esaurito'],
    [1, 'low_stock', 'Ultimo pezzo'],
    [2, 'low_stock', 'Ultimi 2 pezzi'],
    [3, 'low_stock', 'Ultimi 3 pezzi'],
    [4, 'available', 'Disponibile'],
]);

it('computes the admin stock status with the low stock threshold', function (int $stock, string $status, string $label) {
    $product = new Product(['stock' => $stock]);

    expect($product->stock_status)->toBe(['status' => $status, 'label' => $label]);
})->with([
    [0, 'out_of_stock', 'Esaurito'],
    [1, 'low_stock', 'Ultimo pezzo'],
    [4, 'low_stock', 'Ultimi 4 pezzi'],
    [5, 'low_stock', 'Ultimi 5 pezzi'],
    [6, 'available', 'Disponibile'],
]);

it('caps the max quantity at the stock and at ten', function (int $stock, int $maxQuantity) {
    expect((new Product(['stock' => $stock]))->max_quantity)->toBe($maxQuantity);
})->with([
    [0, 0],
    [3, 3],
    [10, 10],
    [25, 10],
]);

it('is new when created within the last thirty days', function (string $createdAt, bool $isNew) {
    $this->travelTo(Carbon::parse('2026-09-29 12:00', 'Europe/Rome'));

    $product = new Product;
    $product->created_at = Carbon::parse($createdAt, 'Europe/Rome');

    expect($product->is_new)->toBe($isNew);
})->with([
    'created today' => ['2026-09-29 09:00', true],
    'created 29 days ago' => ['2026-08-31 12:00', true],
    'created 31 days ago' => ['2026-08-29 12:00', false],
]);
