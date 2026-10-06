<?php

use App\Actions\Checkout\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentResult;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScriptedPaymentGateway;

beforeEach(function () {
    Notification::fake();
});

function approvedCard(): array
{
    return ['holder' => 'Giulia Ferri', 'number' => '4242424242424242', 'expiry' => '08/29', 'cvc' => '123'];
}

it('cancels the order and gives the stock back when the gateway throws', function () {
    $user = User::factory()->customer()->create();
    $address = Address::factory()->for($user)->create();
    $product = shopProduct(['stock' => 6]);
    cartRow($user, $product, 2);
    useGateway(new ScriptedPaymentGateway(onCharge: fn () => throw new RuntimeException('Gateway timeout')));

    expect(fn () => app(PlaceOrderAction::class)->execute($user, $address->id, approvedCard()))
        ->toThrow(RuntimeException::class, 'Gateway timeout');

    $order = Order::query()->sole();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->paid_at)->toBeNull()
        ->and($product->fresh()->stock)->toBe(6)
        ->and(CartItem::query()->count())->toBe(1)
        ->and($order->statusChanges()->where('to_status', OrderStatus::Cancelled)->value('reason'))->toBe('Servizio di pagamento non disponibile');

    expect(Payment::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('refuses an account anonymized in the meantime, without effects', function () {
    $user = User::factory()->customer()->create();
    $address = Address::factory()->for($user)->create();
    $product = shopProduct(['stock' => 6]);
    cartRow($user, $product, 2);
    $gateway = useGateway(new ScriptedPaymentGateway);
    anonymizeBehindTheSession($user);

    expect(fn () => app(PlaceOrderAction::class)->execute($user, $address->id, approvedCard()))
        ->toThrow(AuthenticationException::class);

    expect(Order::query()->count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(6)
        ->and($gateway->charges)->toBe(0);
});

it('charges the order with its own total', function () {
    $user = User::factory()->customer()->create();
    $address = Address::factory()->for($user)->create();
    cartRow($user, shopProduct(['price' => 2900]), 2);
    $charged = null;
    useGateway(new ScriptedPaymentGateway(onCharge: function (Order $order) use (&$charged) {
        $charged = [$order->status, $order->total, $order->number];

        return PaymentResult::success('test_charge', '4242');
    }));

    $order = app(PlaceOrderAction::class)->execute($user, $address->id, approvedCard());

    expect($charged)->toBe([OrderStatus::Pending, 6490, $order->number])
        ->and($order->status)->toBe(OrderStatus::Paid);
});

it('refuses as an invalid field an address deleted after the validation', function () {
    $user = User::factory()->customer()->create();
    $address = Address::factory()->for($user)->create();
    $product = shopProduct(['stock' => 6]);
    cartRow($user, $product, 2);
    $address->delete();

    try {
        app(PlaceOrderAction::class)->execute($user, $address->id, approvedCard());
        $this->fail('The deleted address was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['address_id' => ['Scegli un indirizzo di spedizione valido.']]);
    }

    expect(Order::query()->count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(6);
});
