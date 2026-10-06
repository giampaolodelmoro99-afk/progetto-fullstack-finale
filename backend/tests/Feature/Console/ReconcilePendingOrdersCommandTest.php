<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Services\Payment\FakePaymentGateway;
use App\Support\PaymentResult;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScriptedPaymentGateway;

beforeEach(function () {
    $this->travelTo(Carbon\Carbon::parse('2026-09-28 19:00:00', 'Europe/Rome'));
    Notification::fake();
});

/**
 * A checkout interrupted after the first transaction: pending order with
 * the stock already taken (2 pieces, 4 left) and the cart still full.
 *
 * @return array{0: Order, 1: Product, 2: User}
 */
function interruptedCheckout(string $createdAt = '2026-09-28 18:41:00'): array
{
    $user = User::factory()->customer()->create();
    $product = shopProduct(['price' => 34900, 'stock' => 4]);
    cartRow($user, $product, 2);
    $order = Order::factory()
        ->pending()
        ->for($user)
        ->has(OrderItem::factory()->for($product)->state(['quantity' => 2]), 'items')
        ->create(['created_at' => $createdAt]);

    return [$order, $product, $user];
}

function chargeReachedTheGateway(Order $order, string $number): void
{
    app(FakePaymentGateway::class)->charge($order, ['holder' => 'Giulia Ferri', 'number' => $number, 'expiry' => '08/29', 'cvc' => '123']);
}

it('completes as paid an order whose charge the gateway approved', function () {
    [$order, $product, $user] = interruptedCheckout();
    chargeReachedTheGateway($order, '4242424242424242');

    $this->artisan('orders:reconcile-pending')->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at->toDateTimeString())->toBe('2026-09-28 19:00:00')
        ->and($product->fresh()->stock)->toBe(4)
        ->and(CartItem::query()->count())->toBe(0);

    $payment = Payment::query()->sole();
    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->card_last_four)->toBe('4242')
        ->and($payment->amount)->toBe(69800)
        ->and($payment->provider_reference)->toStartWith('fake_ch_');

    $change = OrderStatusChange::query()->whereBelongsTo($order)->latest('id')->first();
    expect([$change->from_status, $change->to_status, $change->changed_by])->toBe([OrderStatus::Pending, OrderStatus::Paid, null]);

    Notification::assertSentTo($user, OrderPlacedNotification::class);
    Notification::assertSentTimes(OrderPlacedNotification::class, 1);
});

it('cancels an order whose charge the gateway rejected and gives the stock back', function () {
    [$order, $product] = interruptedCheckout();
    chargeReachedTheGateway($order, '4000000000000002');

    $this->artisan('orders:reconcile-pending')->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->paid_at)->toBeNull()
        ->and($product->fresh()->stock)->toBe(6)
        ->and(CartItem::query()->count())->toBe(1)
        ->and($order->statusChanges()->where('to_status', OrderStatus::Cancelled)->value('reason'))->toBe('Carta rifiutata dalla banca');

    $payment = Payment::query()->sole();
    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->card_last_four)->toBe('0002');
    Notification::assertNothingSent();
});

it('cancels an order whose charge never reached the gateway, without a payment row', function () {
    [$order, $product] = interruptedCheckout();

    $this->artisan('orders:reconcile-pending')->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(6)
        ->and(Payment::query()->count())->toBe(0)
        ->and($order->statusChanges()->where('to_status', OrderStatus::Cancelled)->value('reason'))->toBe('Pagamento non ricevuto dal servizio di pagamento');
});

it('leaves alone a pending order whose payment may still be in progress', function () {
    [$order, $product] = interruptedCheckout('2026-09-28 18:46:00');
    chargeReachedTheGateway($order, '4242424242424242');

    $this->artisan('orders:reconcile-pending')
        ->expectsOutputToContain('No pending orders to reconcile.')
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($product->fresh()->stock)->toBe(4)
        ->and(Payment::query()->count())->toBe(0)
        ->and(CartItem::query()->count())->toBe(1);
});

it('has no double effect when it runs again', function () {
    [$approved, $approvedProduct] = interruptedCheckout();
    chargeReachedTheGateway($approved, '4242424242424242');
    [$declined, $declinedProduct] = interruptedCheckout();
    chargeReachedTheGateway($declined, '4000000000000002');

    $this->artisan('orders:reconcile-pending')->assertSuccessful();
    $this->artisan('orders:reconcile-pending')
        ->expectsOutputToContain('No pending orders to reconcile.')
        ->assertSuccessful();

    expect($approved->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($declined->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($approvedProduct->fresh()->stock)->toBe(4)
        ->and($declinedProduct->fresh()->stock)->toBe(6)
        ->and(Payment::query()->count())->toBe(2)
        ->and(OrderStatusChange::query()->whereIn('order_id', [$approved->id, $declined->id])->count())->toBe(4);
    Notification::assertSentTimes(OrderPlacedNotification::class, 1);
});

it('does not touch the orders that are no longer pending', function () {
    $paid = Order::factory()->paid()->create(['created_at' => '2026-09-01 10:00:00']);
    $failed = Order::factory()->failedPayment()->create(['created_at' => '2026-09-01 10:00:00']);
    $gateway = useGateway(new ScriptedPaymentGateway);

    $this->artisan('orders:reconcile-pending')->assertSuccessful();

    expect($gateway->charges)->toBe(0)
        ->and($paid->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($failed->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('leaves the order pending when the gateway is unreachable and goes on with the others', function () {
    [$unreachable, $unreachableProduct] = interruptedCheckout();
    [$approved] = interruptedCheckout();
    useGateway(new ScriptedPaymentGateway(onCharge: function (Order $order) use ($unreachable) {
        if ($order->id === $unreachable->id) {
            throw new RuntimeException('Gateway timeout');
        }

        return PaymentResult::success('test_charge', '4242');
    }));

    $this->artisan('orders:reconcile-pending')->assertFailed();

    expect($unreachable->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($unreachableProduct->fresh()->stock)->toBe(4)
        ->and($approved->fresh()->status)->toBe(OrderStatus::Paid);
});
