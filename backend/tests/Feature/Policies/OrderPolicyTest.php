<?php

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;

it('lets the customer see and cancel only the own orders paid at least once', function (string $ability, string $state, bool $allowed) {
    $owner = User::factory()->customer()->create();
    $order = Order::factory()->{$state}()->for($owner)->create();

    $response = (new OrderPolicy)->{$ability}($owner, $order);

    expect($response->allowed())->toBe($allowed)
        ->and($response->status())->toBe($allowed ? null : 404);
})->with(['view', 'cancel'])->with([
    'paid' => ['paid', true],
    'shipped' => ['shipped', true],
    'delivered' => ['delivered', true],
    'cancelled after payment' => ['cancelledAfterPayment', true],
    'failed payment' => ['failedPayment', false],
    'pending' => ['pending', false],
]);

it('answers 404 for the order of another customer', function (string $ability) {
    $order = Order::factory()->paid()->create();

    $response = (new OrderPolicy)->{$ability}(User::factory()->customer()->create(), $order);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
})->with(['view', 'cancel']);

it('lets the admin see every order, failed attempts included', function (string $state) {
    $order = Order::factory()->{$state}()->create();

    expect((new OrderPolicy)->view(User::factory()->admin()->create(), $order)->allowed())->toBeTrue();
})->with(['paid', 'failedPayment', 'pending']);

it('never lets the admin use the cancellation of the customer', function () {
    $order = Order::factory()->paid()->create();

    $response = (new OrderPolicy)->cancel(User::factory()->admin()->create(), $order);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});
