<?php

use App\Models\Address;
use App\Models\User;

it('refuses addresses and cart to an administrator', function (string $method, string $uri) {
    $admin = actingAsAdmin();
    $address = Address::factory()->for($admin)->create();
    $row = cartRow($admin, shopProduct(), 1);
    $uri = str_replace(['{address}', '{cartItem}'], [(string) $address->id, (string) $row->id], $uri);

    fromFrontend()->json($method, $uri, [...addressPayload(), 'product_id' => $row->product_id, 'quantity' => 2])
        ->assertForbidden()
        ->assertExactJson(['message' => 'Gli account di amministrazione non possono fare acquisti.']);

    $this->assertModelExists($address);
    expect($row->fresh()->quantity)->toBe(1)
        ->and($admin->addresses()->count())->toBe(1);
})->with([
    ['GET', '/api/v1/addresses'],
    ['POST', '/api/v1/addresses'],
    ['GET', '/api/v1/addresses/{address}'],
    ['PATCH', '/api/v1/addresses/{address}'],
    ['DELETE', '/api/v1/addresses/{address}'],
    ['GET', '/api/v1/cart'],
    ['DELETE', '/api/v1/cart'],
    ['POST', '/api/v1/cart/items'],
    ['PATCH', '/api/v1/cart/items/{cartItem}'],
    ['DELETE', '/api/v1/cart/items/{cartItem}'],
]);

it('answers 403 to an administrator before revealing whether a resource of somebody else exists', function (string $method, string $uri) {
    $customer = User::factory()->create();
    $address = Address::factory()->for($customer)->create();
    $row = cartRow($customer, shopProduct(), 1);
    actingAsAdmin();

    fromFrontend()->json($method, str_replace(['{address}', '{cartItem}'], [(string) $address->id, (string) $row->id], $uri), ['quantity' => 2])
        ->assertForbidden();
})->with([
    ['GET', '/api/v1/addresses/{address}'],
    ['PATCH', '/api/v1/cart/items/{cartItem}'],
]);
