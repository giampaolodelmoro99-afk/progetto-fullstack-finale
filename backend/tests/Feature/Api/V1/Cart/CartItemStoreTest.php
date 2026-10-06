<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;

it('rejects a guest', function () {
    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => shopProduct()->id, 'quantity' => 1])
        ->assertUnauthorized();
});

it('creates the cart at the first insert and answers with the whole cart', function () {
    $user = actingAsCustomer();
    $product = shopProduct(['name' => 'Cuffie Aria', 'price' => 34900]);

    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertCreated()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product.name', 'Cuffie Aria')
        ->assertJsonPath('data.items.0.quantity', 1)
        ->assertJsonPath('data.summary.subtotal', 34900)
        ->assertJsonPath('data.can_checkout', true);

    $cart = Cart::query()->whereBelongsTo($user)->sole();
    $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
});

it('lets a customer with an unverified email use the cart', function () {
    actingAsCustomer(User::factory()->customer()->unverified()->create());

    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => shopProduct()->id, 'quantity' => 2])
        ->assertCreated();
});

it('adds the quantity to a product already in the cart', function () {
    $user = actingAsCustomer();
    $product = shopProduct(['price' => 2900]);
    cartRow($user, $product, 2);

    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 3])
        ->assertCreated()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 5)
        ->assertJsonPath('data.summary.subtotal', 14500);

    expect(CartItem::query()->count())->toBe(1);
});

it('refuses a quantity above the maximum of the product', function (int $stock, int $inCart, int $added, string $message) {
    $user = actingAsCustomer();
    $product = shopProduct(['stock' => $stock]);

    if ($inCart > 0) {
        cartRow($user, $product, $inCart);
    }

    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => $added])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity' => $message]);

    expect((int) CartItem::query()->sum('quantity'))->toBe($inCart);
})->with([
    'above the stock' => [3, 0, 4, 'Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.'],
    'above the limit per row' => [25, 0, 11, 'Puoi avere al massimo 10 pezzi di questo prodotto nel carrello.'],
    'far above a low stock' => [3, 0, 11, 'Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.'],
    'sum above the stock' => [3, 2, 2, 'Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.'],
    'sum above the limit per row' => [25, 8, 3, 'Puoi avere al massimo 10 pezzi di questo prodotto nel carrello.'],
    'sum above the last piece' => [1, 1, 1, 'Puoi avere al massimo 1 pezzo di questo prodotto nel carrello.'],
]);

it('accepts a sum equal to the maximum', function () {
    $user = actingAsCustomer();
    $product = shopProduct(['stock' => 25]);
    cartRow($user, $product, 7);

    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 3])
        ->assertCreated()
        ->assertJsonPath('data.items.0.quantity', 10);
});

it('refuses a product that cannot be bought', function (array $attributes) {
    actingAsCustomer();
    $product = shopProduct($attributes);

    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_id' => 'Questo prodotto non è disponibile.']);

    expect(Cart::query()->count())->toBe(0);
})->with([
    'deactivated' => [['is_active' => false]],
    'deleted' => [['deleted_at' => '2026-09-01 10:00:00']],
    'out of stock' => [['stock' => 0]],
]);

it('validates the request', function (array $body, string $field) {
    actingAsCustomer();
    $product = shopProduct();

    fromFrontend()->postJson('/api/v1/cart/items', [...['product_id' => $product->id, 'quantity' => 1], ...$body])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing product' => [['product_id' => null], 'product_id'],
    'unknown product' => [['product_id' => 999999], 'product_id'],
    'missing quantity' => [['quantity' => null], 'quantity'],
    'quantity 0' => [['quantity' => 0], 'quantity'],
    'negative quantity' => [['quantity' => -1], 'quantity'],
    'decimal quantity' => [['quantity' => 1.5], 'quantity'],
]);

it('leaves the cart of another user untouched', function () {
    $other = User::factory()->create();
    $product = shopProduct();
    cartRow($other, $product, 2);
    actingAsCustomer();

    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertCreated()
        ->assertJsonPath('data.items.0.quantity', 1);

    $this->assertDatabaseHas('cart_items', ['cart_id' => $other->cart->id, 'product_id' => $product->id, 'quantity' => 2]);
});
