<?php

use App\Models\User;
use App\Policies\CartItemPolicy;

it('lets a user change only the rows of the own cart and answers 404 for the others', function (string $ability) {
    $owner = User::factory()->create();
    $row = cartRow($owner, shopProduct(), 1);
    $other = User::factory()->create();
    cartRow($other, shopProduct(), 1);
    $policy = new CartItemPolicy;

    $own = $policy->{$ability}($owner, $row);
    $foreign = $policy->{$ability}($other, $row);
    $admin = $policy->{$ability}(User::factory()->admin()->create(), $row);

    expect($own->allowed())->toBeTrue()
        ->and($foreign->allowed())->toBeFalse()
        ->and($foreign->status())->toBe(404)
        ->and($admin->allowed())->toBeFalse()
        ->and($admin->status())->toBe(404);
})->with(['update', 'delete']);
