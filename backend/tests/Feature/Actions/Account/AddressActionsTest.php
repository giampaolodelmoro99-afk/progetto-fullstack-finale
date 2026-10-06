<?php

use App\Actions\Account\CreateAddressAction;
use App\Actions\Account\DeleteAddressAction;
use App\Actions\Account\UpdateAddressAction;
use App\Models\Address;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('creates no address for an account anonymized in the meantime', function () {
    $user = User::factory()->create();
    anonymizeBehindTheSession($user);

    expect(fn () => app(CreateAddressAction::class)->execute($user, addressPayload()))
        ->toThrow(AuthenticationException::class);

    expect(Address::query()->count())->toBe(0);
});

it('changes no address of an account anonymized in the meantime', function () {
    $user = User::factory()->create();
    $default = Address::factory()->for($user)->asDefault()->create(['city' => 'Pisa']);
    $address = Address::factory()->for($user)->create(['city' => 'Lucca']);
    anonymizeBehindTheSession($user);

    expect(fn () => app(UpdateAddressAction::class)->execute($address, ['city' => 'Firenze', 'is_default' => true]))
        ->toThrow(AuthenticationException::class);

    expect($address->fresh()->city)->toBe('Lucca')
        ->and($address->fresh()->is_default)->toBeFalse()
        ->and($default->fresh()->is_default)->toBeTrue();
});

it('deletes no address of an account anonymized in the meantime', function () {
    $user = User::factory()->create();
    $address = Address::factory()->for($user)->asDefault()->create();
    anonymizeBehindTheSession($user);

    expect(fn () => app(DeleteAddressAction::class)->execute($address))
        ->toThrow(AuthenticationException::class);

    $this->assertModelExists($address);
});

it('answers 401 to an anonymized account that still has a session', function () {
    actingAsCustomer(User::factory()->anonymized()->create());

    fromFrontend()->postJson('/api/v1/addresses', addressPayload())
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Devi accedere per continuare.']);

    expect(Address::query()->count())->toBe(0);
});

it('answers 404 when the address was deleted by another request', function (string $action) {
    $user = User::factory()->create();
    $address = Address::factory()->for($user)->asDefault()->create();
    Address::query()->whereKey($address->id)->delete();

    expect(fn () => $action === 'update'
        ? app(UpdateAddressAction::class)->execute($address, ['city' => 'Firenze'])
        : app(DeleteAddressAction::class)->execute($address)
    )->toThrow(ModelNotFoundException::class);
})->with(['update', 'delete']);
