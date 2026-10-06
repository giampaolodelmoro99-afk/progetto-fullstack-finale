<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

it('lists only the orders of the customer paid at least once', function () {
    $user = actingAsVerifiedCustomer();
    $paid = Order::factory()->paid()->for($user)->create();
    $shipped = Order::factory()->shipped()->for($user)->create();
    $cancelledAfterPayment = Order::factory()->cancelledAfterPayment()->for($user)->create();
    Order::factory()->failedPayment()->for($user)->create();
    Order::factory()->pending()->for($user)->create();
    Order::factory()->paid()->for(User::factory()->customer())->create();

    $response = fromFrontend()->getJson('/api/v1/orders')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
        ->toBe([$paid->id, $shipped->id, $cancelledAfterPayment->id]);
});

it('lists the newest orders first, then by id', function () {
    $user = actingAsVerifiedCustomer();
    $older = Order::factory()->paid()->for($user)->create(['created_at' => '2026-09-02 10:12:00']);
    $sameTimeFirst = Order::factory()->paid()->for($user)->create(['created_at' => '2026-09-28 18:41:00']);
    $sameTimeSecond = Order::factory()->paid()->for($user)->create(['created_at' => '2026-09-28 18:41:00']);
    $middle = Order::factory()->paid()->for($user)->create(['created_at' => '2026-09-15 09:00:00']);

    $response = fromFrontend()->getJson('/api/v1/orders')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$sameTimeSecond->id, $sameTimeFirst->id, $middle->id, $older->id]);
});

it('shows the summary of each order with the number of items', function () {
    $user = actingAsVerifiedCustomer();
    $order = Order::factory()
        ->paid()
        ->for($user)
        ->has(OrderItem::factory()->state(['unit_price' => 34900, 'quantity' => 1]), 'items')
        ->has(OrderItem::factory()->state(['unit_price' => 5900, 'quantity' => 2]), 'items')
        ->has(OrderItem::factory()->state(['unit_price' => 2900, 'quantity' => 1]), 'items')
        ->create(['created_at' => '2026-09-28 18:41:00']);

    $response = fromFrontend()->getJson('/api/v1/orders')->assertOk();

    expect($response->json('data.0'))->toMatchArray([
        'id' => $order->id,
        'number' => $order->number,
        'status' => ['value' => 'paid', 'label' => 'Pagato'],
        'created_at' => '2026-09-28T18:41:00+02:00',
        'paid_at' => '2026-09-28T18:42:00+02:00',
        'subtotal' => 49600,
        'shipping_cost' => 0,
        'total' => 49600,
        'items_count' => 4,
        'can_cancel' => true,
    ]);
    $response->assertJsonMissingPath('data.0.items')
        ->assertJsonMissingPath('data.0.status_history')
        ->assertJsonMissingPath('data.0.payment');
});

it('pages the orders ten at a time', function () {
    $user = actingAsVerifiedCustomer();
    Order::factory()->count(11)->paid()->for($user)->create();

    fromFrontend()->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 11)
        ->assertJsonPath('meta.last_page', 2);

    fromFrontend()->getJson('/api/v1/orders?page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('answers with an empty list to a customer without orders', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

it('rejects a guest', function () {
    fromFrontend()->getJson('/api/v1/orders')->assertUnauthorized();
});

it('rejects an administrator, who has no purchases', function () {
    actingAsAdmin();

    fromFrontend()->getJson('/api/v1/orders')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Gli account di amministrazione non possono fare acquisti.']);
});
