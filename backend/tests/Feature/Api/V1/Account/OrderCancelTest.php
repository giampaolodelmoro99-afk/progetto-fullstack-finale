<?php

use App\Actions\Orders\CancelOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Orders\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Support\PaymentResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScriptedPaymentGateway;

beforeEach(function () {
    $this->travelTo(Carbon\Carbon::parse('2026-09-29 10:00:00', 'Europe/Rome'));
    Notification::fake();
});

/**
 * A paid order of the user with two rows: 2 pieces of the first product and
 * 1 of the second.
 *
 * @return array{0: Order, 1: Product, 2: Product}
 */
function paidOrderWithStock(User $user): array
{
    $headphones = shopProduct(['price' => 34900, 'stock' => 5]);
    $cable = shopProduct(['price' => 2900, 'stock' => 0]);
    $order = Order::factory()
        ->paid()
        ->for($user)
        ->has(OrderItem::factory()->for($headphones)->state(['quantity' => 2]), 'items')
        ->has(OrderItem::factory()->for($cable)->state(['quantity' => 1]), 'items')
        ->create(['created_at' => '2026-09-28 18:41:00']);

    return [$order, $headphones, $cable];
}

it('cancels a paid order with the refund and gives the stock back', function () {
    $user = actingAsVerifiedCustomer();
    [$order, $headphones, $cable] = paidOrderWithStock($user);
    $gateway = useGateway(new ScriptedPaymentGateway);

    $response = fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', ['value' => 'cancelled', 'label' => 'Annullato'])
        ->assertJsonPath('data.can_cancel', false)
        ->assertJsonPath('data.payment', ['card_last_four' => '4242']);

    expect($response->json('data.status_history.2'))->toBe([
        'status' => ['value' => 'cancelled', 'label' => 'Annullato'],
        'description' => 'Annullato e rimborsato',
        'created_at' => '2026-09-29T10:00:00+02:00',
    ]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancelled_at->toDateTimeString())->toBe('2026-09-29 10:00:00')
        ->and($order->paid_at)->not->toBeNull()
        ->and($headphones->fresh()->stock)->toBe(7)
        ->and($cable->fresh()->stock)->toBe(1)
        ->and(Payment::query()->whereBelongsTo($order)->sole()->status)->toBe(PaymentStatus::Refunded)
        ->and($gateway->refunds)->toBe(1);

    $change = OrderStatusChange::query()->whereBelongsTo($order)->latest('id')->first();
    expect([$change->from_status, $change->to_status, $change->changed_by, $change->reason])
        ->toBe([OrderStatus::Paid, OrderStatus::Cancelled, $user->id, null]);

    Notification::assertSentTo($user, OrderCancelledNotification::class);
    Notification::assertSentTimes(OrderCancelledNotification::class, 1);
});

it('gives the stock back also to a product deleted after the purchase', function () {
    $user = actingAsVerifiedCustomer();
    [$order, $headphones] = paidOrderWithStock($user);
    $headphones->delete();

    fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();

    expect(Product::withTrashed()->findOrFail($headphones->id)->stock)->toBe(7);
});

it('changes nothing when the refund fails', function () {
    $user = actingAsVerifiedCustomer();
    [$order, $headphones, $cable] = paidOrderWithStock($user);
    $historyRows = OrderStatusChange::query()->count();
    useGateway(new ScriptedPaymentGateway(onRefund: fn () => PaymentResult::failure('Rimborso rifiutato')));

    fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertStatus(502)
        ->assertExactJson([
            'message' => 'Il rimborso non è riuscito: l\'ordine non è stato annullato. Riprova tra poco.',
            'code' => 'refund_failed',
        ]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->cancelled_at)->toBeNull()
        ->and($headphones->fresh()->stock)->toBe(5)
        ->and($cable->fresh()->stock)->toBe(0)
        ->and(Payment::query()->whereBelongsTo($order)->sole()->status)->toBe(PaymentStatus::Succeeded)
        ->and(OrderStatusChange::query()->count())->toBe($historyRows);
    Notification::assertNothingSent();
});

it('refuses an order that can no longer be cancelled, without calling the gateway', function (string $state, OrderStatus $status) {
    $user = actingAsVerifiedCustomer();
    $order = Order::factory()->{$state}()->for($user)->create();
    $historyRows = OrderStatusChange::query()->count();
    $gateway = useGateway(new ScriptedPaymentGateway);

    fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Lo stato attuale dell\'ordine non consente questa operazione.',
            'code' => 'invalid_order_transition',
        ]);

    expect($gateway->refunds)->toBe(0)
        ->and($order->fresh()->status)->toBe($status)
        ->and(OrderStatusChange::query()->count())->toBe($historyRows);
    Notification::assertNothingSent();
})->with([
    'shipped' => ['shipped', OrderStatus::Shipped],
    'delivered' => ['delivered', OrderStatus::Delivered],
    'already cancelled' => ['cancelledAfterPayment', OrderStatus::Cancelled],
]);

it('refuses a pending order in the action, without calling the gateway', function () {
    $order = Order::factory()->pending()->create();
    $gateway = useGateway(new ScriptedPaymentGateway);

    expect(fn () => app(CancelOrderAction::class)->execute($order, $order->user))
        ->toThrow(InvalidOrderTransitionException::class);

    expect($gateway->refunds)->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('hides the orders the customer cannot cancel', function (Closure $order, OrderStatus $status) {
    actingAsVerifiedCustomer();
    $gateway = useGateway(new ScriptedPaymentGateway);
    $target = $order();

    fromFrontend()->postJson("/api/v1/orders/{$target->id}/cancel")->assertNotFound();

    expect($gateway->refunds)->toBe(0)
        ->and($target->fresh()->status)->toBe($status);
})->with([
    'of another customer' => [fn () => Order::factory()->paid()->for(User::factory()->customer())->create(), OrderStatus::Paid],
    'failed payment' => [fn () => Order::factory()->failedPayment()->for(auth()->user())->create(), OrderStatus::Cancelled],
    'pending' => [fn () => Order::factory()->pending()->for(auth()->user())->create(), OrderStatus::Pending],
]);

it('neither refunds nor cancels twice', function () {
    $user = actingAsVerifiedCustomer();
    [$order, $headphones] = paidOrderWithStock($user);
    $gateway = useGateway(new ScriptedPaymentGateway);

    fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();
    fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")->assertUnprocessable();

    expect($gateway->refunds)->toBe(1)
        ->and($headphones->fresh()->stock)->toBe(7)
        ->and(OrderStatusChange::query()->whereBelongsTo($order)->where('to_status', OrderStatus::Cancelled)->count())->toBe(1);
    Notification::assertSentTimes(OrderCancelledNotification::class, 1);
});

it('leaves the order to a concurrent cancellation that settled it first', function () {
    $user = User::factory()->customer()->create();
    [$order, $headphones] = paidOrderWithStock($user);
    useGateway(new ScriptedPaymentGateway(onRefund: function (Payment $payment) use ($order) {
        Order::query()->whereKey($order->id)->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
        Payment::query()->whereKey($payment->id)->update(['status' => PaymentStatus::Refunded]);

        return PaymentResult::success('test_refund', '4242');
    }));

    $result = app(CancelOrderAction::class)->execute($order, $user);

    expect($result->status)->toBe(OrderStatus::Cancelled)
        ->and($headphones->fresh()->stock)->toBe(5)
        ->and(OrderStatusChange::query()->whereBelongsTo($order)->where('to_status', OrderStatus::Cancelled)->count())->toBe(0);
    Notification::assertNothingSent();
});

it('logs a warning when the order was shipped between the refund and the lock', function () {
    Log::spy();
    $user = User::factory()->customer()->create();
    [$order] = paidOrderWithStock($user);
    useGateway(new ScriptedPaymentGateway(onRefund: function () use ($order) {
        Order::query()->whereKey($order->id)->update(['status' => OrderStatus::Shipped, 'shipped_at' => now()]);

        return PaymentResult::success('test_refund', '4242');
    }));

    $result = app(CancelOrderAction::class)->execute($order, $user);

    expect($result->status)->toBe(OrderStatus::Shipped);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'Refunded payment of an order that was not cancelled.'
        && $context === ['order_id' => $order->id, 'payment_id' => Payment::query()->whereBelongsTo($order)->sole()->id, 'status' => 'shipped']);
});

it('rejects a guest', function () {
    $order = Order::factory()->paid()->create();

    fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")->assertUnauthorized();
});

it('rejects an administrator on the customer route', function () {
    actingAsAdmin();
    $order = Order::factory()->paid()->create();

    fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});
