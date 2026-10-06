<?php

use App\Actions\Checkout\SettleOrderPaymentAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentResult;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([OrderPlaced::class]);
});

/*
| Checkout and reconciliation can settle the same pending order: the second
| one works on an instance read while the order was still pending.
*/
it('does nothing when a success arrives for an order already paid by the other path', function () {
    $user = User::factory()->customer()->create();
    $order = Order::factory()->pending()->for($user)->create();
    $stale = Order::query()->findOrFail($order->id);
    $settle = app(SettleOrderPaymentAction::class);

    $settle->execute($order, PaymentResult::success('test_charge', '4242'));
    cartRow($user, shopProduct(), 1);

    $result = $settle->execute($stale, PaymentResult::success('test_charge', '4242'));

    expect($result->id)->toBe($order->id)
        ->and($result->status)->toBe(OrderStatus::Paid)
        ->and(Payment::query()->whereBelongsTo($order)->count())->toBe(1)
        ->and($order->statusChanges()->get()->map(fn ($change) => [$change->from_status, $change->to_status])->all())->toBe([
            [null, OrderStatus::Pending],
            [OrderStatus::Pending, OrderStatus::Paid],
        ])
        ->and(CartItem::query()->count())->toBe(1);
    Event::assertDispatchedTimes(OrderPlaced::class, 1);
});

it('does nothing when a failure arrives for an order already paid by the other path', function () {
    $user = User::factory()->customer()->create();
    $product = shopProduct(['stock' => 7]);
    $order = Order::factory()->pending()->for($user)
        ->has(OrderItem::factory()->for($product)->state(['quantity' => 2]), 'items')
        ->create();
    $stale = Order::query()->findOrFail($order->id);
    $settle = app(SettleOrderPaymentAction::class);

    $settle->execute($order, PaymentResult::success('test_charge', '4242'));

    $result = $settle->execute($stale, PaymentResult::failure('Carta rifiutata dalla banca', '0002'));

    expect($result->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->cancelled_at)->toBeNull()
        ->and($product->fresh()->stock)->toBe(7)
        ->and(Payment::query()->whereBelongsTo($order)->sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and($order->statusChanges()->where('to_status', OrderStatus::Cancelled)->exists())->toBeFalse();
});
