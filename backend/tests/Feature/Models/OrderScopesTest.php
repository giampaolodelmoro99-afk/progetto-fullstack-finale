<?php

use App\Models\Order;
use App\Models\User;

it('shows the customer only the orders paid at least once', function () {
    $paid = Order::factory()->paid()->create();
    $cancelledAfterPayment = Order::factory()->cancelledAfterPayment()->create();
    Order::factory()->pending()->create();
    Order::factory()->failedPayment()->create();

    expect(Order::visibleToCustomer()->orderBy('id')->pluck('id')->all())
        ->toBe([$paid->id, $cancelledAfterPayment->id]);
});

it('finds the failed payment attempts', function () {
    $failed = Order::factory()->failedPayment()->create();
    Order::factory()->cancelledAfterPayment()->create();
    Order::factory()->pending()->create();

    expect(Order::failedPayments()->pluck('id')->all())->toBe([$failed->id]);
});

it('finds the orders paid in a month in italian time', function (string $paidAt, bool $included) {
    Order::factory()->paid()->create(['created_at' => '2026-08-20 10:00', 'paid_at' => $paidAt]);

    expect(Order::paidInMonth('2026-09')->exists())->toBe($included);
})->with([
    'first day at 00:30' => ['2026-09-01 00:30:00', true],
    'last day at 23:59:59' => ['2026-09-30 23:59:59', true],
    'previous month at 23:30' => ['2026-08-31 23:30:00', false],
    'next month at 00:00' => ['2026-10-01 00:00:00', false],
]);

it('never counts failed attempts and pending orders in a month', function () {
    $this->travelTo('2026-09-15 12:00');
    Order::factory()->failedPayment()->create();
    Order::factory()->pending()->create();

    expect(Order::paidInMonth('2026-09')->count())->toBe(0);
});

it('searches by order number or customer email', function () {
    $this->travelTo('2026-09-15 12:00');
    $giulia = User::factory()->create(['email' => 'giulia.ferri@example.com']);
    $byEmail = Order::factory()->for($giulia)->create();
    $other = Order::factory()->create();

    expect(Order::search('giulia.ferri')->pluck('id')->all())->toBe([$byEmail->id])
        ->and(Order::search($other->number)->pluck('id')->all())->toBe([$other->id])
        ->and(Order::search('ORD-2026')->count())->toBe(2);
});
