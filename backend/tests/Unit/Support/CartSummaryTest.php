<?php

use App\Models\CartItem;
use App\Models\Product;
use App\Support\CartSummary;
use Illuminate\Support\Collection;

function summaryRow(int $price, int $quantity, int $stock = 20, bool $active = true, bool $trashed = false): CartItem
{
    $product = new Product(['price' => $price, 'stock' => $stock, 'is_active' => $active]);

    if ($trashed) {
        $product->deleted_at = now();
    }

    return (new CartItem(['quantity' => $quantity]))->setRelation('product', $product);
}

function summaryOf(CartItem ...$items): CartSummary
{
    return CartSummary::fromItems(new Collection($items));
}

it('gives free shipping to the cart of the purchase mockup', function () {
    $summary = summaryOf(summaryRow(34900, 1), summaryRow(5900, 2), summaryRow(2900, 1));

    expect($summary->itemsCount)->toBe(4)
        ->and($summary->subtotal)->toBe(49600)
        ->and($summary->shippingCost)->toBe(0)
        ->and($summary->total)->toBe(49600)
        ->and($summary->freeShippingThreshold)->toBe(9900)
        ->and($summary->freeShippingRemaining)->toBe(0)
        ->and($summary->canCheckout)->toBeTrue();
});

it('charges the shipping below the threshold, as in the small cart of the mockup', function () {
    $summary = summaryOf(summaryRow(2900, 2));

    expect($summary->itemsCount)->toBe(2)
        ->and($summary->subtotal)->toBe(5800)
        ->and($summary->shippingCost)->toBe(690)
        ->and($summary->total)->toBe(6490)
        ->and($summary->freeShippingRemaining)->toBe(4100);
});

it('computes what is missing for free shipping', function (int $price, int $shipping, int $remaining) {
    $summary = summaryOf(summaryRow($price, 1));

    expect($summary->shippingCost)->toBe($shipping)
        ->and($summary->freeShippingRemaining)->toBe($remaining);
})->with([
    'below the threshold' => [5800, 690, 4100],
    'one cent below' => [9899, 690, 1],
    'at the threshold' => [9900, 0, 0],
    'above the threshold' => [12000, 0, 0],
]);

it('ships nothing and shows no remainder without purchasable rows', function (bool $outOfStockRows) {
    $items = $outOfStockRows ? [summaryRow(2900, 1, stock: 0), summaryRow(34900, 2, stock: 0)] : [];
    $summary = summaryOf(...$items);

    expect($summary->subtotal)->toBe(0)
        ->and($summary->shippingCost)->toBe(0)
        ->and($summary->total)->toBe(0)
        ->and($summary->freeShippingRemaining)->toBeNull();
})->with([
    'empty cart' => [false],
    'only out of stock rows' => [true],
]);

it('counts every row but sums only the purchasable ones', function () {
    $summary = summaryOf(
        summaryRow(34900, 1),
        summaryRow(5900, 2),
        summaryRow(2900, 1),
        summaryRow(12900, 1, stock: 0),
        summaryRow(4900, 3, active: false),
        summaryRow(1900, 2, trashed: true),
    );

    expect($summary->itemsCount)->toBe(10)
        ->and($summary->subtotal)->toBe(49600)
        ->and($summary->total)->toBe(49600);
});

it('keeps a row above the stock in the totals', function () {
    $summary = summaryOf(summaryRow(5900, 2, stock: 1));

    expect($summary->subtotal)->toBe(11800)
        ->and($summary->shippingCost)->toBe(0)
        ->and($summary->total)->toBe(11800)
        ->and($summary->canCheckout)->toBeFalse();
});

it('allows the checkout only with purchasable rows within the stock', function (array $items, bool $canCheckout) {
    expect(CartSummary::fromItems(new Collection($items))->canCheckout)->toBe($canCheckout);
})->with([
    'empty' => fn () => [[], false],
    'all purchasable' => fn () => [[summaryRow(2900, 2), summaryRow(34900, 1)], true],
    'an out of stock row' => fn () => [[summaryRow(2900, 2), summaryRow(34900, 1, stock: 0)], false],
    'a deactivated row' => fn () => [[summaryRow(2900, 2), summaryRow(34900, 1, active: false)], false],
    'a deleted row' => fn () => [[summaryRow(2900, 2), summaryRow(34900, 1, trashed: true)], false],
    'a row above the stock' => fn () => [[summaryRow(2900, 3, stock: 2)], false],
]);

it('gives a line total only to purchasable rows', function (CartItem $item, ?int $lineTotal) {
    expect(CartSummary::lineTotal($item))->toBe($lineTotal);
})->with([
    'purchasable' => fn () => [summaryRow(5900, 2), 11800],
    'above the stock' => fn () => [summaryRow(5900, 2, stock: 1), 11800],
    'out of stock' => fn () => [summaryRow(5900, 2, stock: 0), null],
    'deactivated' => fn () => [summaryRow(5900, 2, active: false), null],
    'deleted' => fn () => [summaryRow(5900, 2, trashed: true), null],
]);
