<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Carbon;

it('formats the order number from year and id', function (int $id, string $createdAt, string $number) {
    expect(Order::formatNumber($id, Carbon::parse($createdAt, 'Europe/Rome')))->toBe($number);
})->with([
    [42, '2026-09-28 18:41', 'ORD-2026-000042'],
    [1, '2027-01-01 00:10', 'ORD-2027-000001'],
    [123456, '2026-12-31 23:59', 'ORD-2026-123456'],
]);

it('can be cancelled only when paid', function (OrderStatus $status, bool $canCancel) {
    expect((new Order(['status' => $status]))->can_cancel)->toBe($canCancel);
})->with([
    [OrderStatus::Pending, false],
    [OrderStatus::Paid, true],
    [OrderStatus::Shipped, false],
    [OrderStatus::Delivered, false],
    [OrderStatus::Cancelled, false],
]);

it('is a failed payment only when cancelled without paid_at', function (OrderStatus $status, ?string $paidAt, bool $failed) {
    $order = new Order(['status' => $status, 'paid_at' => $paidAt]);

    expect($order->failed_payment)->toBe($failed);
})->with([
    'cancelled never paid' => [OrderStatus::Cancelled, null, true],
    'cancelled after payment' => [OrderStatus::Cancelled, '2026-09-28 18:42', false],
    'pending' => [OrderStatus::Pending, null, false],
    'paid' => [OrderStatus::Paid, '2026-09-28 18:42', false],
]);
