<?php

use App\Models\CartItem;
use App\Models\Product;

function cartItemFor(int $quantity, int $stock, bool $active = true, bool $trashed = false): CartItem
{
    $product = new Product(['stock' => $stock, 'is_active' => $active]);

    if ($trashed) {
        $product->deleted_at = now();
    }

    return (new CartItem(['quantity' => $quantity]))->setRelation('product', $product);
}

it('is purchasable only for an active, not deleted product in stock', function (CartItem $item, bool $purchasable) {
    expect($item->is_purchasable)->toBe($purchasable);
})->with([
    'active in stock' => fn () => [cartItemFor(1, 5), true],
    'active beyond stock' => fn () => [cartItemFor(3, 1), true],
    'out of stock' => fn () => [cartItemFor(1, 0), false],
    'inactive' => fn () => [cartItemFor(1, 5, active: false), false],
    'soft deleted' => fn () => [cartItemFor(1, 5, trashed: true), false],
]);

it('exceeds the stock when the quantity is greater than the current stock', function (int $quantity, int $stock, bool $exceeds) {
    expect(cartItemFor($quantity, $stock)->exceeds_stock)->toBe($exceeds);
})->with([
    [2, 1, true],
    [2, 2, false],
    [1, 5, false],
    [1, 0, true],
]);
