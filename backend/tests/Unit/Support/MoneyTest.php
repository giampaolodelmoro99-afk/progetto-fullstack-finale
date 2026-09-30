<?php

use App\Support\Money;

it('formats cents as an italian price', function (int $cents, string $expected) {
    expect(Money::format($cents))->toBe($expected);
})->with([
    [690, '6,90 €'],
    [9900, '99,00 €'],
    [34900, '349,00 €'],
    [124900, '1.249,00 €'],
    [999999, '9.999,99 €'],
    [0, '0,00 €'],
]);
