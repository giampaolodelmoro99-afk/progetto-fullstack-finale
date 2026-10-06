<?php

use App\Models\Address;
use App\Models\User;
use App\Policies\AddressPolicy;

it('lets a user manage only the own addresses and answers 404 for the others', function (string $ability) {
    $owner = User::factory()->create();
    $address = Address::factory()->for($owner)->create();
    $policy = new AddressPolicy;

    $own = $policy->{$ability}($owner, $address);
    $foreign = $policy->{$ability}(User::factory()->create(), $address);
    $admin = $policy->{$ability}(User::factory()->admin()->create(), $address);

    expect($own->allowed())->toBeTrue()
        ->and($foreign->allowed())->toBeFalse()
        ->and($foreign->status())->toBe(404)
        ->and($admin->allowed())->toBeFalse()
        ->and($admin->status())->toBe(404);
})->with(['view', 'update', 'delete']);

it('lets every user create an address', function () {
    expect((new AddressPolicy)->create(User::factory()->create()))->toBeTrue();
});
