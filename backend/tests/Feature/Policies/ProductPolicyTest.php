<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;

it('lets everybody view an active product and answers 404 for a deactivated one', function (?string $role, bool $active) {
    $user = match ($role) {
        'customer' => User::factory()->customer()->create(),
        'admin' => User::factory()->admin()->create(),
        default => null,
    };
    $product = Product::factory()->for(Category::factory())->create(['is_active' => $active]);

    $response = (new ProductPolicy)->viewInShop($user, $product);

    expect($response->allowed())->toBe($active)
        ->and($response->status())->toBe($active ? null : 404);
})->with([
    'guest' => [null],
    'customer' => ['customer'],
    'admin' => ['admin'],
])->with([
    'active' => [true],
    'deactivated' => [false],
]);

it('gives the admin abilities on products to administrators only', function (string $role, bool $allowed) {
    $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->customer()->create();
    $product = Product::factory()->for(Category::factory())->inactive()->create();
    $policy = new ProductPolicy;

    expect($policy->viewAny($user))->toBe($allowed)
        ->and($policy->view($user, $product))->toBe($allowed)
        ->and($policy->create($user))->toBe($allowed)
        ->and($policy->update($user, $product))->toBe($allowed)
        ->and($policy->delete($user, $product))->toBe($allowed);
})->with([
    'admin' => ['admin', true],
    'customer' => ['customer', false],
]);
