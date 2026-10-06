<?php

use App\Models\Cart;
use App\Models\Category;
use App\Models\ProductImage;
use App\Models\User;

it('rejects a guest', function () {
    fromFrontend()->getJson('/api/v1/cart')->assertUnauthorized();
});

it('answers with an empty cart without creating it', function () {
    $user = actingAsCustomer();

    fromFrontend()->getJson('/api/v1/cart')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'items' => [],
                'summary' => [
                    'items_count' => 0,
                    'subtotal' => 0,
                    'shipping_cost' => 0,
                    'total' => 0,
                    'free_shipping_threshold' => 9900,
                    'free_shipping_remaining' => null,
                ],
                'can_checkout' => false,
            ],
        ]);

    expect(Cart::query()->whereBelongsTo($user)->exists())->toBeFalse();
});

it('shows the cart of the purchase mockup with free shipping and the blocked checkout', function () {
    $user = actingAsCustomer();
    $audio = Category::factory()->create(['name' => 'Audio']);
    $charging = Category::factory()->create(['name' => 'Ricarica ed energia']);
    $headphones = shopProduct(['name' => 'Cuffie Aria', 'price' => 34900, 'stock' => 18, 'category_id' => $audio->id]);
    $charger = shopProduct(['name' => 'Caricatore Brezza 65 W', 'price' => 5900, 'stock' => 12, 'category_id' => $charging->id]);
    $cable = shopProduct(['name' => 'Cavo Seta USB-C 1 m', 'price' => 2900, 'stock' => 2, 'category_id' => $charging->id]);
    $radio = shopProduct(['name' => 'Radio Onda Portatile', 'price' => 7900, 'stock' => 0, 'category_id' => $audio->id]);
    ProductImage::factory()->for($headphones)->create(['path' => 'products/cuffie-aria.webp', 'alt' => 'Cuffie Aria di lato']);
    $headphonesRow = cartRow($user, $headphones, 1);
    cartRow($user, $charger, 2);
    cartRow($user, $cable, 1);
    $radioRow = cartRow($user, $radio, 1);

    $response = fromFrontend()->getJson('/api/v1/cart')->assertOk();

    expect($response->json('data.items'))->toHaveCount(4)
        ->and($response->json('data.items.0'))->toBe([
            'id' => $headphonesRow->id,
            'quantity' => 1,
            'max_quantity' => 10,
            'exceeds_stock' => false,
            'is_purchasable' => true,
            'unit_price' => 34900,
            'line_total' => 34900,
            'product' => [
                'name' => 'Cuffie Aria',
                'slug' => 'cuffie-aria',
                'category' => ['name' => 'Audio', 'slug' => 'audio'],
                'cover' => ['url' => 'http://localhost:8000/storage/products/cuffie-aria.webp', 'alt' => 'Cuffie Aria di lato'],
                'availability' => ['status' => 'available', 'label' => 'Disponibile'],
            ],
        ])
        ->and($response->json('data.items.1.line_total'))->toBe(11800)
        ->and($response->json('data.items.1.product.category'))->toBe(['name' => 'Ricarica ed energia', 'slug' => 'ricarica-ed-energia'])
        ->and($response->json('data.items.2.max_quantity'))->toBe(2)
        ->and($response->json('data.items.2.product.availability'))->toBe(['status' => 'low_stock', 'label' => 'Ultimi 2 pezzi'])
        ->and($response->json('data.items.3'))->toBe([
            'id' => $radioRow->id,
            'quantity' => 1,
            'max_quantity' => 0,
            'exceeds_stock' => true,
            'is_purchasable' => false,
            'unit_price' => 7900,
            'line_total' => null,
            'product' => [
                'name' => 'Radio Onda Portatile',
                'slug' => 'radio-onda-portatile',
                'category' => ['name' => 'Audio', 'slug' => 'audio'],
                'cover' => null,
                'availability' => ['status' => 'out_of_stock', 'label' => 'Esaurito'],
            ],
        ])
        ->and($response->json('data.summary'))->toBe([
            'items_count' => 5,
            'subtotal' => 49600,
            'shipping_cost' => 0,
            'total' => 49600,
            'free_shipping_threshold' => 9900,
            'free_shipping_remaining' => 0,
        ])
        ->and($response->json('data.can_checkout'))->toBeFalse();
});

it('charges the shipping below the threshold and shows what is missing', function () {
    $user = actingAsCustomer();
    cartRow($user, shopProduct(['name' => 'Cavo Seta USB-C 1 m', 'price' => 2900]), 2);

    fromFrontend()->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.summary', [
            'items_count' => 2,
            'subtotal' => 5800,
            'shipping_cost' => 690,
            'total' => 6490,
            'free_shipping_threshold' => 9900,
            'free_shipping_remaining' => 4100,
        ])
        ->assertJsonPath('data.can_checkout', true);
});

it('ships nothing and shows no remainder with only out of stock rows', function () {
    $user = actingAsCustomer();
    cartRow($user, shopProduct(['price' => 2900, 'stock' => 0]), 2);
    cartRow($user, shopProduct(['price' => 34900, 'stock' => 0]), 1);

    fromFrontend()->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.summary', [
            'items_count' => 3,
            'subtotal' => 0,
            'shipping_cost' => 0,
            'total' => 0,
            'free_shipping_threshold' => 9900,
            'free_shipping_remaining' => null,
        ])
        ->assertJsonPath('data.can_checkout', false);
});

it('counts the deactivated and deleted rows but leaves them out of the totals', function () {
    $user = actingAsCustomer();
    cartRow($user, shopProduct(['price' => 2900]), 1);
    cartRow($user, shopProduct(['price' => 4900, 'is_active' => false]), 2);
    cartRow($user, shopProduct(['price' => 1900, 'deleted_at' => now()]), 3);

    fromFrontend()->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.1.is_purchasable', false)
        ->assertJsonPath('data.items.1.line_total', null)
        ->assertJsonPath('data.items.2.is_purchasable', false)
        ->assertJsonPath('data.items.2.unit_price', 1900)
        ->assertJsonPath('data.summary.items_count', 6)
        ->assertJsonPath('data.summary.subtotal', 2900)
        ->assertJsonPath('data.summary.total', 3590)
        ->assertJsonPath('data.can_checkout', false);
});

it('keeps a row above the stock in the totals and blocks the checkout', function () {
    $user = actingAsCustomer();
    cartRow($user, shopProduct(['price' => 5900, 'stock' => 1]), 2);

    fromFrontend()->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.exceeds_stock', true)
        ->assertJsonPath('data.items.0.is_purchasable', true)
        ->assertJsonPath('data.items.0.max_quantity', 1)
        ->assertJsonPath('data.items.0.line_total', 11800)
        ->assertJsonPath('data.summary.subtotal', 11800)
        ->assertJsonPath('data.can_checkout', false);
});

it('caps the maximum quantity of a row', function (int $stock, int $maxQuantity) {
    $user = actingAsCustomer();
    cartRow($user, shopProduct(['stock' => $stock]), 1);

    fromFrontend()->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.max_quantity', $maxQuantity);
})->with([
    [3, 3],
    [10, 10],
    [25, 10],
]);

it('always uses the current price of the product', function () {
    $user = actingAsCustomer();
    $product = shopProduct(['price' => 5900]);
    cartRow($user, $product, 2);

    fromFrontend()->getJson('/api/v1/cart')->assertJsonPath('data.summary.subtotal', 11800);

    $product->update(['price' => 4900]);

    fromFrontend()->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.unit_price', 4900)
        ->assertJsonPath('data.items.0.line_total', 9800)
        ->assertJsonPath('data.summary.subtotal', 9800)
        ->assertJsonPath('data.summary.shipping_cost', 690)
        ->assertJsonPath('data.summary.total', 10490);
});

it('shows only the cart of the user', function () {
    $user = actingAsCustomer();
    cartRow(User::factory()->create(), shopProduct(), 3);
    cartRow($user, shopProduct(['price' => 2900]), 1);

    fromFrontend()->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.summary.items_count', 1);
});
