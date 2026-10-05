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
