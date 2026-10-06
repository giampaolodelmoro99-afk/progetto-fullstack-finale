<?php

use App\Models\User;

it('rejects a guest', function () {
    $row = cartRow(User::factory()->create(), shopProduct(), 1);

    fromFrontend()->deleteJson("/api/v1/cart/items/{$row->id}")->assertUnauthorized();

    $this->assertModelExists($row);
});

it('removes a row and answers with the whole cart', function () {
    $user = actingAsCustomer();
    $kept = cartRow($user, shopProduct(['price' => 2900]), 2);
    $removed = cartRow($user, shopProduct(['price' => 34900]), 1);

    fromFrontend()->deleteJson("/api/v1/cart/items/{$removed->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $kept->id)
        ->assertJsonPath('data.summary.subtotal', 5800)
        ->assertJsonPath('data.summary.free_shipping_remaining', 4100);

    $this->assertModelMissing($removed);
});

it('removes a row that cannot be bought', function () {
    $user = actingAsCustomer();
    $row = cartRow($user, shopProduct(['deleted_at' => '2026-09-01 10:00:00']), 1);

    fromFrontend()->deleteJson("/api/v1/cart/items/{$row->id}")
        ->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.can_checkout', false);

    $this->assertModelMissing($row);
});

it('answers 404 for a row of another user and leaves it in place', function () {
    $row = cartRow(User::factory()->create(), shopProduct(), 1);
    actingAsCustomer();

    fromFrontend()->deleteJson("/api/v1/cart/items/{$row->id}")
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);

    $this->assertModelExists($row);
});

it('answers 404 for a row that does not exist', function () {
    actingAsCustomer();

    fromFrontend()->deleteJson('/api/v1/cart/items/999999')->assertNotFound();
});
