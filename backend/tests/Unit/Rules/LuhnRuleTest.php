<?php

use App\Rules\LuhnRule;

it('checks the Luhn checksum of a card number', function (string $number, bool $valid) {
    expect(LuhnRule::passes($number))->toBe($valid);
})->with([
    'approved test card' => ['4242424242424242', true],
    'declined test card' => ['4000000000000002', true],
    'wrong last digit' => ['4242424242424241', false],
    'letters' => ['424242424242424A', false],
    'empty' => ['', false],
]);

it('fails the validation with the italian message', function () {
    $messages = [];

    (new LuhnRule)->validate('payment.number', '4242424242424241', function (string $message) use (&$messages) {
        $messages[] = $message;
    });

    expect($messages)->toBe(['Il numero della carta non è valido.']);
});
