<?php

use App\Models\Address;
use App\Models\User;

it('rejects a guest on every route', function (string $method, string $uri) {
    $address = Address::factory()->create();

    fromFrontend()->json($method, str_replace('{address}', (string) $address->id, $uri), addressPayload())
        ->assertUnauthorized();
})->with([
    ['GET', '/api/v1/addresses'],
    ['POST', '/api/v1/addresses'],
    ['GET', '/api/v1/addresses/{address}'],
    ['PATCH', '/api/v1/addresses/{address}'],
    ['DELETE', '/api/v1/addresses/{address}'],
]);

it('lists only the addresses of the user, the default one first', function () {
    $user = actingAsCustomer();
    $lucca = Address::factory()->for($user)->create(['city' => 'Lucca']);
    $pisa = Address::factory()->for($user)->asDefault()->create(['city' => 'Pisa']);
    $firenze = Address::factory()->for($user)->create(['city' => 'Firenze']);
    Address::factory()->asDefault()->create(['city' => 'Torino']);

    $response = fromFrontend()->getJson('/api/v1/addresses')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$pisa->id, $lucca->id, $firenze->id]);
});

it('creates the first address as the default one, in Italy', function () {
    $user = actingAsCustomer();

    $response = fromFrontend()->postJson('/api/v1/addresses', addressPayload())->assertCreated();

    expect($response->json('data'))->toBe([
        'id' => $user->addresses()->sole()->id,
        'full_name' => 'Giulia Ferri',
        'street' => 'Via San Martino 42',
        'city' => 'Pisa',
        'postal_code' => '56125',
        'province' => 'PI',
        'country' => 'IT',
        'phone' => '+39 333 123 4567',
        'is_default' => true,
    ]);
});

it('always stores IT as the country', function () {
    $user = actingAsCustomer();

    fromFrontend()->postJson('/api/v1/addresses', addressPayload(['country' => 'FR']))
        ->assertCreated()
        ->assertJsonPath('data.country', 'IT');

    $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'country' => 'IT']);
});

it('creates a further address that is not the default one', function () {
    $user = actingAsCustomer();
    $default = Address::factory()->for($user)->asDefault()->create();

    fromFrontend()->postJson('/api/v1/addresses', addressPayload(['city' => 'Lucca', 'postal_code' => '55100', 'province' => 'LU']))
        ->assertCreated()
        ->assertJsonPath('data.is_default', false);

    expect($default->fresh()->is_default)->toBeTrue();
});

it('moves the default to a new address marked as default, only for the same user', function () {
    $user = actingAsCustomer();
    $previous = Address::factory()->for($user)->asDefault()->create();
    $otherDefault = Address::factory()->asDefault()->create();

    fromFrontend()->postJson('/api/v1/addresses', addressPayload(['is_default' => true]))
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    expect($previous->fresh()->is_default)->toBeFalse()
        ->and($otherDefault->fresh()->is_default)->toBeTrue()
        ->and($user->addresses()->where('is_default', true)->count())->toBe(1);
});

it('accepts valid italian data', function (array $fields) {
    actingAsCustomer();

    fromFrontend()->postJson('/api/v1/addresses', addressPayload($fields))->assertCreated();
})->with([
    'postal code' => [['postal_code' => '56125']],
    'province' => [['province' => 'PI']],
    'lowercase province' => [['province' => 'pi']],
    'phone with prefix and spaces' => [['phone' => '+39 333 123 4567']],
    'phone with digits only' => [['phone' => '3331234567']],
    'phone of 20 characters' => [['phone' => '+39 333 123 4567 890']],
    'city of 100 characters' => [['city' => str_repeat('a', 100)]],
]);

it('refuses data that is not valid on the right field', function (array $fields, string $field) {
    $user = actingAsCustomer();

    fromFrontend()->postJson('/api/v1/addresses', addressPayload($fields))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($user->addresses()->count())->toBe(0);
})->with([
    'postal code of 4 digits' => [['postal_code' => '5612'], 'postal_code'],
    'postal code of 6 digits' => [['postal_code' => '561255'], 'postal_code'],
    'postal code with a letter' => [['postal_code' => '5612A'], 'postal_code'],
    'province of 3 letters' => [['province' => 'PIS'], 'province'],
    'province with a digit' => [['province' => 'P1'], 'province'],
    'missing phone' => [['phone' => null], 'phone'],
    'phone of 21 characters' => [['phone' => '+39 333 123 4567 8901'], 'phone'],
    'phone with letters' => [['phone' => '+39 333 ABC 4567'], 'phone'],
    'plus not at the start' => [['phone' => '39+333 123 4567'], 'phone'],
    'missing name' => [['full_name' => null], 'full_name'],
    'name of 256 characters' => [['full_name' => str_repeat('a', 256)], 'full_name'],
    'missing street' => [['street' => null], 'street'],
    'missing city' => [['city' => null], 'city'],
    'city of 101 characters' => [['city' => str_repeat('a', 101)], 'city'],
    'default not boolean' => [['is_default' => 'forse'], 'is_default'],
]);

it('explains the format errors in italian', function () {
    actingAsCustomer();

    fromFrontend()->postJson('/api/v1/addresses', addressPayload(['postal_code' => '5612', 'province' => 'P1', 'phone' => '39+333']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'postal_code' => 'Il CAP deve avere 5 cifre.',
            'province' => 'Usa la sigla di due lettere della provincia, per esempio PI.',
            'phone' => 'Usa solo cifre, spazi e un + iniziale.',
        ]);
});

it('stores the province code in uppercase', function (string $province) {
    $user = actingAsCustomer();

    fromFrontend()->postJson('/api/v1/addresses', addressPayload(['province' => $province]))
        ->assertCreated()
        ->assertJsonPath('data.province', 'PI');

    $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'province' => 'PI']);
})->with(['pi', 'Pi', ' pi ']);

it('uppercases the province code in an update too', function () {
    $user = actingAsCustomer();
    $address = Address::factory()->for($user)->create(['province' => 'PI']);

    fromFrontend()->patchJson("/api/v1/addresses/{$address->id}", ['province' => 'lu'])
        ->assertOk()
        ->assertJsonPath('data.province', 'LU');
});

it('shows an address of the user', function () {
    $user = actingAsCustomer();
    $address = Address::factory()->for($user)->create(['city' => 'Lucca']);

    fromFrontend()->getJson("/api/v1/addresses/{$address->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $address->id)
        ->assertJsonPath('data.city', 'Lucca');
});

it('updates every field sent, keeping the country', function () {
    $user = actingAsCustomer();
    $address = Address::factory()->for($user)->asDefault()->create();

    fromFrontend()->patchJson("/api/v1/addresses/{$address->id}", addressPayload([
        'full_name' => 'Giulia Ferri',
        'street' => 'Via dei Servi 15',
        'city' => 'Firenze',
        'postal_code' => '50122',
        'province' => 'FI',
        'country' => 'DE',
    ]))
        ->assertOk()
        ->assertJsonPath('data.street', 'Via dei Servi 15')
        ->assertJsonPath('data.city', 'Firenze')
        ->assertJsonPath('data.postal_code', '50122')
        ->assertJsonPath('data.province', 'FI')
        ->assertJsonPath('data.country', 'IT')
        ->assertJsonPath('data.is_default', true);
});

it('updates only the fields sent', function () {
    $user = actingAsCustomer();
    $address = Address::factory()->for($user)->create(['city' => 'Lucca', 'phone' => '+39 333 123 4567']);

    fromFrontend()->patchJson("/api/v1/addresses/{$address->id}", ['phone' => '3331234567'])
        ->assertOk()
        ->assertJsonPath('data.phone', '3331234567')
        ->assertJsonPath('data.city', 'Lucca');
});

it('validates the fields sent in an update', function (array $fields, string $field) {
    $user = actingAsCustomer();
    $address = Address::factory()->for($user)->create(['postal_code' => '56125']);

    fromFrontend()->patchJson("/api/v1/addresses/{$address->id}", $fields)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($address->fresh()->postal_code)->toBe('56125');
})->with([
    'postal code of 4 digits' => [['postal_code' => '5612'], 'postal_code'],
    'empty name' => [['full_name' => ''], 'full_name'],
    'province of 3 letters' => [['province' => 'PIS'], 'province'],
]);

it('makes an address the default one with is_default alone', function () {
    $user = actingAsCustomer();
    $previous = Address::factory()->for($user)->asDefault()->create();
    $address = Address::factory()->for($user)->create(['city' => 'Lucca']);
    $otherDefault = Address::factory()->asDefault()->create();

    fromFrontend()->patchJson("/api/v1/addresses/{$address->id}", ['is_default' => true])
        ->assertOk()
        ->assertJsonPath('data.is_default', true)
        ->assertJsonPath('data.city', 'Lucca');

    expect($previous->fresh()->is_default)->toBeFalse()
        ->and($otherDefault->fresh()->is_default)->toBeTrue()
        ->and($user->addresses()->where('is_default', true)->count())->toBe(1);
});

it('ignores is_default false on the default address', function () {
    $user = actingAsCustomer();
    $default = Address::factory()->for($user)->asDefault()->create();
    Address::factory()->for($user)->create();

    fromFrontend()->patchJson("/api/v1/addresses/{$default->id}", ['is_default' => false])
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect($default->fresh()->is_default)->toBeTrue();
});

it('deletes an address that is not the default one', function () {
    $user = actingAsCustomer();
    $default = Address::factory()->for($user)->asDefault()->create();
    $address = Address::factory()->for($user)->create();

    fromFrontend()->deleteJson("/api/v1/addresses/{$address->id}")->assertNoContent();

    $this->assertModelMissing($address);
    expect($default->fresh()->is_default)->toBeTrue();
});

it('makes the most recent remaining address the default when the default is deleted', function () {
    $user = actingAsCustomer();
    $older = Address::factory()->for($user)->create();
    $default = Address::factory()->for($user)->asDefault()->create();
    $newer = Address::factory()->for($user)->create();
    $otherAddress = Address::factory()->create();

    fromFrontend()->deleteJson("/api/v1/addresses/{$default->id}")->assertNoContent();

    $this->assertModelMissing($default);
    expect($newer->fresh()->is_default)->toBeTrue()
        ->and($older->fresh()->is_default)->toBeFalse()
        ->and($otherAddress->fresh()->is_default)->toBeFalse();
});

it('deletes the last address of the user', function () {
    $user = actingAsCustomer();
    $address = Address::factory()->for($user)->asDefault()->create();

    fromFrontend()->deleteJson("/api/v1/addresses/{$address->id}")->assertNoContent();

    expect($user->addresses()->count())->toBe(0);
});

it('answers 404 for an address of another user and leaves it untouched', function (string $method) {
    $address = Address::factory()->for(User::factory())->create(['city' => 'Torino']);
    actingAsCustomer();

    fromFrontend()->json($method, "/api/v1/addresses/{$address->id}", ['city' => 'Pisa'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);

    expect($address->fresh()->city)->toBe('Torino');
})->with(['GET', 'PATCH', 'DELETE']);

it('answers 404 for an address that does not exist', function () {
    actingAsCustomer();

    fromFrontend()->getJson('/api/v1/addresses/999999')->assertNotFound();
});
