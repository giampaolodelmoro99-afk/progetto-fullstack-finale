<?php

use App\Models\User;

it('rejects a guest', function () {
    $row = cartRow(User::factory()->create(), shopProduct(), 1);

    fromFrontend()->patchJson("/api/v1/cart/items/{$row->id}", ['quantity' => 2])->assertUnauthorized();
});

it('changes the quantity and answers with the whole cart', function () {
    $user = actingAsCustomer();
    $row = cartRow($user, shopProduct(['price' => 2900]), 1);

    fromFrontend()->patchJson("/api/v1/cart/items/{$row->id}", ['quantity' => 4])
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $row->id)
        ->assertJsonPath('data.items.0.quantity', 4)
        ->assertJsonPath('data.summary.subtotal', 11600)
        ->assertJsonPath('data.summary.shipping_cost', 0);

    expect($row->fresh()->quantity)->toBe(4);
});

it('brings a row above the stock back within it', function () {
    $user = actingAsCustomer();
    $row = cartRow($user, shopProduct(['price' => 5900, 'stock' => 1]), 2);

    fromFrontend()->patchJson("/api/v1/cart/items/{$row->id}", ['quantity' => 1])
        ->assertOk()
        ->assertJsonPath('data.items.0.exceeds_stock', false)
        ->assertJsonPath('data.can_checkout', true);
});

it('refuses a quantity above the maximum of the product', function (int $stock, int $quantity, string $message) {
    $user = actingAsCustomer();
    $row = cartRow($user, shopProduct(['stock' => $stock]), 1);

    fromFrontend()->patchJson("/api/v1/cart/items/{$row->id}", ['quantity' => $quantity])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity' => $message]);

    expect($row->fresh()->quantity)->toBe(1);
})->with([
    'above the stock' => [3, 4, 'Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.'],
    'above the limit per row' => [25, 11, 'Puoi avere al massimo 10 pezzi di questo prodotto nel carrello.'],
    'far above a low stock' => [3, 11, 'Puoi avere al massimo 3 pezzi di questo prodotto nel carrello.'],
    'above the last piece' => [1, 2, 'Puoi avere al massimo 1 pezzo di questo prodotto nel carrello.'],
]);

it('refuses a quantity that is not valid', function (mixed $quantity) {
    $user = actingAsCustomer();
    $row = cartRow($user, shopProduct(), 2);

    fromFrontend()->patchJson("/api/v1/cart/items/{$row->id}", ['quantity' => $quantity])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity']);

    expect($row->fresh()->quantity)->toBe(2);
})->with([
    'missing' => [null],
    'zero' => [0],
    'negative' => [-3],
    'text' => ['due'],
]);

it('refuses to change a row that cannot be bought any more', function (array $attributes, int $quantity) {
    $user = actingAsCustomer();
    $row = cartRow($user, shopProduct($attributes), 1);

    fromFrontend()->patchJson("/api/v1/cart/items/{$row->id}", ['quantity' => $quantity])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity' => 'Questo prodotto non è più disponibile: rimuovilo dal carrello.']);

    expect($row->fresh()->quantity)->toBe(1);
})->with([
    'deactivated' => [['is_active' => false], 2],
    'deactivated, above every maximum' => [['is_active' => false], 11],
    'deleted' => [['deleted_at' => '2026-09-01 10:00:00'], 2],
    'out of stock' => [['stock' => 0], 2],
]);

it('answers 404 for a row of another user and leaves it untouched', function () {
    $row = cartRow(User::factory()->create(), shopProduct(), 1);
    actingAsCustomer();

    fromFrontend()->patchJson("/api/v1/cart/items/{$row->id}", ['quantity' => 2])
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);

    expect($row->fresh()->quantity)->toBe(1);
});

it('answers 404 for a row that does not exist', function () {
    actingAsCustomer();

    fromFrontend()->patchJson('/api/v1/cart/items/999999', ['quantity' => 2])->assertNotFound();
});
