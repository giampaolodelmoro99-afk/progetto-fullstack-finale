<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;

/**
 * @return list<array{0: string|null, 1: string, 2: int|null, 3: string|null, 4: string}>
 */
function historyOf(Order $order): array
{
    return $order->statusChanges()->get()
        ->map(fn ($change) => [
            $change->from_status?->value,
            $change->to_status->value,
            $change->changed_by,
            $change->reason,
            $change->created_at->format('Y-m-d H:i'),
        ])
        ->all();
}

beforeEach(function () {
    $this->travelTo('2026-09-10 10:00');
});

it('creates a pending order with number, one item, coherent totals and the creation row', function () {
    $order = Order::factory()->create(['subtotal' => 5800])->fresh();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->number)->toBe(sprintf('ORD-2026-%06d', $order->id))
        ->and($order->shipping_cost)->toBe(690)
        ->and($order->total)->toBe(6490)
        ->and($order->terms_accepted_at->format('Y-m-d H:i'))->toBe('2026-09-10 10:00')
        ->and($order->paid_at)->toBeNull()
        ->and($order->items()->count())->toBe(1)
        ->and($order->items()->first()->line_total)->toBe(5800)
        ->and($order->shipping_address)->toHaveKeys(['full_name', 'street', 'postal_code', 'city', 'province', 'country', 'phone'])
        ->and($order->shipping_address['country'])->toBe('IT')
        ->and($order->payments()->count())->toBe(0)
        ->and(historyOf($order))->toBe([
            [null, 'pending', null, null, '2026-09-10 10:00'],
        ]);
});

it('ships for free from a subtotal of 9900', function () {
    $order = Order::factory()->create(['subtotal' => 9900]);

    expect($order->shipping_cost)->toBe(0)
        ->and($order->total)->toBe(9900);
});

it('recalculates the totals from the attached items', function () {
    $order = Order::factory()
        ->has(OrderItem::factory()->state(['unit_price' => 34900, 'quantity' => 1]), 'items')
        ->has(OrderItem::factory()->state(['unit_price' => 5900, 'quantity' => 2]), 'items')
        ->create()
        ->fresh();

    expect($order->items()->count())->toBe(2)
        ->and($order->subtotal)->toBe(46700)
        ->and($order->shipping_cost)->toBe(0)
        ->and($order->total)->toBe(46700);
});

it('creates a paid order with a succeeded payment', function () {
    $order = Order::factory()->paid()->create(['subtotal' => 34900])->fresh();
    $payment = $order->payments()->sole();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at->format('Y-m-d H:i'))->toBe('2026-09-10 10:01')
        ->and($order->can_cancel)->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->amount)->toBe(34900)
        ->and($payment->card_last_four)->toBe('4242')
        ->and($payment->provider)->toBe('fake')
        ->and($payment->provider_reference)->not->toBeNull()
        ->and(historyOf($order))->toBe([
            [null, 'pending', null, null, '2026-09-10 10:00'],
            ['pending', 'paid', null, null, '2026-09-10 10:01'],
        ]);
});

it('creates a shipped order with the admin as author', function () {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->shipped($admin)->create()->fresh();

    expect($order->status)->toBe(OrderStatus::Shipped)
        ->and($order->shipped_at->format('Y-m-d H:i'))->toBe('2026-09-11 10:01')
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and(historyOf($order))->toBe([
            [null, 'pending', null, null, '2026-09-10 10:00'],
            ['pending', 'paid', null, null, '2026-09-10 10:01'],
            ['paid', 'shipped', $admin->id, null, '2026-09-11 10:01'],
        ]);
});

it('creates an admin as author when none is given', function () {
    $order = Order::factory()->shipped()->create();

    $author = User::find($order->statusChanges()->where('to_status', 'shipped')->value('changed_by'));

    expect($author->isAdmin())->toBeTrue();
});

it('creates a delivered order shipped and delivered by the same admin', function () {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->delivered($admin)->create()->fresh();

    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->delivered_at->format('Y-m-d H:i'))->toBe('2026-09-13 10:01')
        ->and(historyOf($order))->toBe([
            [null, 'pending', null, null, '2026-09-10 10:00'],
            ['pending', 'paid', null, null, '2026-09-10 10:01'],
            ['paid', 'shipped', $admin->id, null, '2026-09-11 10:01'],
            ['shipped', 'delivered', $admin->id, null, '2026-09-13 10:01'],
        ]);

    $unnamed = Order::factory()->delivered()->create();
    $authors = $unnamed->statusChanges()->whereNotNull('changed_by')->pluck('changed_by')->unique();

    expect($authors)->toHaveCount(1);
});

it('creates an order cancelled after payment with a refunded payment, by the customer by default', function () {
    $order = Order::factory()->cancelledAfterPayment()->create()->fresh();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->paid_at->format('Y-m-d H:i'))->toBe('2026-09-10 10:01')
        ->and($order->cancelled_at->format('Y-m-d H:i'))->toBe('2026-09-11 10:01')
        ->and($order->failed_payment)->toBeFalse()
        ->and($order->can_cancel)->toBeFalse()
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Refunded)
        ->and(historyOf($order))->toBe([
            [null, 'pending', null, null, '2026-09-10 10:00'],
            ['pending', 'paid', null, null, '2026-09-10 10:01'],
            ['paid', 'cancelled', $order->user_id, null, '2026-09-11 10:01'],
        ]);
});

it('records the given author of the cancellation', function () {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->cancelledAfterPayment($admin)->create();

    expect($order->statusChanges()->where('to_status', 'cancelled')->value('changed_by'))->toBe($admin->id);
});

it('creates a failed payment attempt cancelled without paid_at', function () {
    $order = Order::factory()->failedPayment()->create(['subtotal' => 34900])->fresh();
    $payment = $order->payments()->sole();

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->paid_at)->toBeNull()
        ->and($order->cancelled_at->format('Y-m-d H:i'))->toBe('2026-09-10 10:01')
        ->and($order->failed_payment)->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->card_last_four)->toBe('0002')
        ->and($payment->provider_reference)->toBeNull()
        ->and($payment->amount)->toBe(34900)
        ->and(historyOf($order))->toBe([
            [null, 'pending', null, null, '2026-09-10 10:00'],
            ['pending', 'cancelled', null, 'Carta rifiutata dalla banca', '2026-09-10 10:01'],
        ]);
});

it('records a custom rejection reason', function () {
    $order = Order::factory()->failedPayment('Fondi insufficienti')->create();

    expect($order->statusChanges()->where('to_status', 'cancelled')->value('reason'))->toBe('Fondi insufficienti');
});

it('derives every timestamp from an explicit creation date', function () {
    $order = Order::factory()->delivered()->create(['created_at' => '2026-05-04 18:30'])->fresh();

    expect($order->number)->toBe(sprintf('ORD-2026-%06d', $order->id))
        ->and($order->paid_at->format('Y-m-d H:i'))->toBe('2026-05-04 18:31')
        ->and($order->shipped_at->format('Y-m-d H:i'))->toBe('2026-05-05 18:31')
        ->and($order->delivered_at->format('Y-m-d H:i'))->toBe('2026-05-07 18:31');
});

it('creates single payments in every status', function () {
    $order = Order::factory()->create(['subtotal' => 12000]);

    $succeeded = Payment::factory()->for($order)->create();
    $failed = Payment::factory()->for($order)->failed()->create();
    $refunded = Payment::factory()->for($order)->refunded()->create();

    expect($succeeded->amount)->toBe(12000)
        ->and($succeeded->status)->toBe(PaymentStatus::Succeeded)
        ->and($failed->card_last_four)->toBe('0002')
        ->and($failed->provider_reference)->toBeNull()
        ->and($refunded->status)->toBe(PaymentStatus::Refunded);
});

it('snapshots name, sku and price of the product in an order row', function () {
    $order = Order::factory()->create();
    $item = $order->items()->first();
    $product = $item->product;

    $row = OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);

    expect($row->product_name)->toBe($product->name)
        ->and($row->product_sku)->toBe($product->sku)
        ->and($row->unit_price)->toBe($product->price)
        ->and($row->line_total)->toBe($product->price * 2);
});

it('writes payment and history once when paid is chained with shipped', function () {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->paid()->shipped($admin)->create()->fresh();

    expect($order->status)->toBe(OrderStatus::Shipped)
        ->and($order->payments()->count())->toBe(1)
        ->and(User::where('role', 'admin')->count())->toBe(1)
        ->and(historyOf($order))->toBe([
            [null, 'pending', null, null, '2026-09-10 10:00'],
            ['pending', 'paid', null, null, '2026-09-10 10:01'],
            ['paid', 'shipped', $admin->id, null, '2026-09-11 10:01'],
        ]);
});

it('creates a single admin when shipped is chained with delivered', function () {
    $order = Order::factory()->shipped()->delivered()->create();

    expect(User::where('role', 'admin')->count())->toBe(1)
        ->and($order->statusChanges()->count())->toBe(4)
        ->and($order->payments()->count())->toBe(1);
});
