<?php

use App\Support\PaymentResult;

it('describes a succeeded charge', function () {
    $result = PaymentResult::success('fake_ch_1', '4242');

    expect($result->succeeded)->toBeTrue()
        ->and($result->reference)->toBe('fake_ch_1')
        ->and($result->cardLastFour)->toBe('4242')
        ->and($result->failureReason)->toBeNull();
});

it('describes a rejected charge with its reason', function () {
    $result = PaymentResult::failure('Carta rifiutata dalla banca', '0002');

    expect($result->succeeded)->toBeFalse()
        ->and($result->reference)->toBeNull()
        ->and($result->cardLastFour)->toBe('0002')
        ->and($result->failureReason)->toBe('Carta rifiutata dalla banca');
});

it('survives the round trip through an array', function () {
    $result = PaymentResult::fromArray(PaymentResult::failure('Carta rifiutata dalla banca', '0002')->toArray());

    expect($result->toArray())->toBe([
        'succeeded' => false,
        'reference' => null,
        'card_last_four' => '0002',
        'failure_reason' => 'Carta rifiutata dalla banca',
    ]);
});
