<?php

use App\Models\Category;
use App\Models\User;
use App\Policies\CategoryPolicy;

it('gives the category abilities to administrators only', function (string $role, bool $allowed) {
    $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->customer()->create();
    $category = Category::factory()->create();
    $policy = new CategoryPolicy;

    expect($policy->viewAny($user))->toBe($allowed)
        ->and($policy->create($user))->toBe($allowed)
        ->and($policy->update($user, $category))->toBe($allowed)
        ->and($policy->delete($user, $category))->toBe($allowed);
})->with([
    'admin' => ['admin', true],
    'customer' => ['customer', false],
]);
