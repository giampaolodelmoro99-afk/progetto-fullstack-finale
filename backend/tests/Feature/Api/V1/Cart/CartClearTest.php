<?php

use App\Models\Cart;
use App\Models\User;

it('rejects a guest', function () {
    fromFrontend()->deleteJson('/api/v1/cart')->assertUnauthorized();
});

it('empties the cart and answers with the empty cart', function () {
    $user = actingAsCustomer();
    cartRow($user, shopProduct(), 2);
    cartRow($user, shopProduct(['stock' => 0]), 1);

    fromFrontend()->deleteJson('/api/v1/cart')
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

    expect(Cart::query()->whereBelongsTo($user)->sole()->items()->count())->toBe(0);
});

it('answers with the empty cart when there is no cart', function () {
    actingAsCustomer();

    fromFrontend()->deleteJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items', []);

    expect(Cart::query()->count())->toBe(0);
});

it('leaves the cart of another user untouched', function () {
    $other = User::factory()->create();
    $row = cartRow($other, shopProduct(), 2);
    $user = actingAsCustomer();
    cartRow($user, shopProduct(), 1);

    fromFrontend()->deleteJson('/api/v1/cart')->assertOk();

    $this->assertModelExists($row);
});
