<?php

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\FakePaymentGateway;

function fakeCard(string $number): array
{
    return ['holder' => 'Giulia Ferri', 'number' => $number, 'expiry' => '08/29', 'cvc' => '123'];
}

it('approves a card with a valid format', function () {
    $result = (new FakePaymentGateway)->charge(Order::factory()->create(), fakeCard('4242424242424242'));

    expect($result->succeeded)->toBeTrue()
        ->and($result->cardLastFour)->toBe('4242')
        ->and($result->reference)->toStartWith('fake_ch_')
        ->and($result->failureReason)->toBeNull();
});

it('rejects the cards ending in 0002 as the bank does', function () {
    $result = (new FakePaymentGateway)->charge(Order::factory()->create(), fakeCard('4000000000000002'));

    expect($result->succeeded)->toBeFalse()
        ->and($result->cardLastFour)->toBe('0002')
        ->and($result->reference)->toBeNull()
        ->and($result->failureReason)->toBe('Carta rifiutata dalla banca');
});

it('returns the first outcome for a charge repeated with the same order', function () {
    $order = Order::factory()->create();
    $first = (new FakePaymentGateway)->charge($order, fakeCard('4242424242424242'));

    $again = (new FakePaymentGateway)->charge($order, fakeCard('4000000000000002'));
    $withoutCard = (new FakePaymentGateway)->charge($order, []);

    expect($again->toArray())->toBe($first->toArray())
        ->and($withoutCard->toArray())->toBe($first->toArray());
});

it('fails a charge without card data that it never received', function () {
    $result = (new FakePaymentGateway)->charge(Order::factory()->create(), []);

    expect($result->succeeded)->toBeFalse()
        ->and($result->cardLastFour)->toBeNull()
        ->and($result->failureReason)->toBe('Pagamento non ricevuto dal servizio di pagamento');
});

it('keeps the outcomes of different orders apart', function () {
    $gateway = new FakePaymentGateway;

    $approved = $gateway->charge(Order::factory()->create(), fakeCard('4242424242424242'));
    $declined = $gateway->charge(Order::factory()->create(), fakeCard('4000000000000002'));

    expect($approved->succeeded)->toBeTrue()
        ->and($declined->succeeded)->toBeFalse();
});

it('always refunds, once for each payment', function () {
    $order = Order::factory()->paid()->create();
    $payment = Payment::query()->whereBelongsTo($order)->sole();

    $first = (new FakePaymentGateway)->refund($payment);
    $again = (new FakePaymentGateway)->refund($payment);
    $other = (new FakePaymentGateway)->refund(Payment::query()->whereBelongsTo(Order::factory()->paid()->create())->sole());

    expect($first->succeeded)->toBeTrue()
        ->and($first->reference)->toStartWith('fake_re_')
        ->and($again->reference)->toBe($first->reference)
        ->and($other->reference)->not->toBe($first->reference);
});
