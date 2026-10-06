<?php

use App\Models\User;
use App\Policies\UserPolicy;

it('lets only customers purchase', function (string $role, bool $allowed) {
    $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->customer()->create();

    $response = (new UserPolicy)->purchase($user);

    expect($response->allowed())->toBe($allowed)
        ->and($response->message())->toBe($allowed ? null : 'Gli account di amministrazione non possono fare acquisti.');
})->with([
    'customer' => ['customer', true],
    'admin' => ['admin', false],
]);
