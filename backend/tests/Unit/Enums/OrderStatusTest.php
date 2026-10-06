<?php

use App\Enums\OrderStatus;

it('has the fixed italian labels', function (OrderStatus $status, string $label) {
    expect($status->label())->toBe($label);
})->with([
    [OrderStatus::Pending, 'In attesa'],
    [OrderStatus::Paid, 'Pagato'],
    [OrderStatus::Shipped, 'Spedito'],
    [OrderStatus::Delivered, 'Consegnato'],
    [OrderStatus::Cancelled, 'Annullato'],
]);

it('has the expected values', function () {
    expect(array_map(fn (OrderStatus $status) => $status->value, OrderStatus::cases()))
        ->toBe(['pending', 'paid', 'shipped', 'delivered', 'cancelled']);
});

it('allows only the transitions of the order lifecycle', function (OrderStatus $from, OrderStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [OrderStatus::Pending, OrderStatus::Pending, false],
    [OrderStatus::Pending, OrderStatus::Paid, true],
    [OrderStatus::Pending, OrderStatus::Shipped, false],
    [OrderStatus::Pending, OrderStatus::Delivered, false],
    [OrderStatus::Pending, OrderStatus::Cancelled, true],
    [OrderStatus::Paid, OrderStatus::Pending, false],
    [OrderStatus::Paid, OrderStatus::Paid, false],
    [OrderStatus::Paid, OrderStatus::Shipped, true],
    [OrderStatus::Paid, OrderStatus::Delivered, false],
    [OrderStatus::Paid, OrderStatus::Cancelled, true],
    [OrderStatus::Shipped, OrderStatus::Pending, false],
    [OrderStatus::Shipped, OrderStatus::Paid, false],
    [OrderStatus::Shipped, OrderStatus::Shipped, false],
    [OrderStatus::Shipped, OrderStatus::Delivered, true],
    [OrderStatus::Shipped, OrderStatus::Cancelled, false],
    [OrderStatus::Delivered, OrderStatus::Pending, false],
    [OrderStatus::Delivered, OrderStatus::Paid, false],
    [OrderStatus::Delivered, OrderStatus::Shipped, false],
    [OrderStatus::Delivered, OrderStatus::Delivered, false],
    [OrderStatus::Delivered, OrderStatus::Cancelled, false],
    [OrderStatus::Cancelled, OrderStatus::Pending, false],
    [OrderStatus::Cancelled, OrderStatus::Paid, false],
    [OrderStatus::Cancelled, OrderStatus::Shipped, false],
    [OrderStatus::Cancelled, OrderStatus::Delivered, false],
    [OrderStatus::Cancelled, OrderStatus::Cancelled, false],
]);

it('maps each status to its timestamp column', function (OrderStatus $status, ?string $column) {
    expect($status->timestampColumn())->toBe($column);
})->with([
    [OrderStatus::Pending, null],
    [OrderStatus::Paid, 'paid_at'],
    [OrderStatus::Shipped, 'shipped_at'],
    [OrderStatus::Delivered, 'delivered_at'],
    [OrderStatus::Cancelled, 'cancelled_at'],
]);

it('offers the admin only shipped, delivered and cancelled', function () {
    expect(OrderStatus::adminTargets())->toBe([OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled]);
});

it('lists the transitions the admin can make from each status', function (OrderStatus $status, array $transitions) {
    expect($status->adminTransitions())->toBe($transitions);
})->with([
    'pending' => [OrderStatus::Pending, []],
    'paid' => [OrderStatus::Paid, [OrderStatus::Shipped, OrderStatus::Cancelled]],
    'shipped' => [OrderStatus::Shipped, [OrderStatus::Delivered]],
    'delivered' => [OrderStatus::Delivered, []],
    'cancelled' => [OrderStatus::Cancelled, []],
]);
