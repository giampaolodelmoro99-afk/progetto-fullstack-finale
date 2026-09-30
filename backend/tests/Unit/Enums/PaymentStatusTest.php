<?php

use App\Enums\PaymentStatus;

it('has the fixed italian labels', function (PaymentStatus $status, string $value, string $label) {
    expect($status->value)->toBe($value)
        ->and($status->label())->toBe($label);
})->with([
    [PaymentStatus::Succeeded, 'succeeded', 'Riuscito'],
    [PaymentStatus::Failed, 'failed', 'Non riuscito'],
    [PaymentStatus::Refunded, 'refunded', 'Rimborsato'],
]);

it('has exactly three cases', function () {
    expect(PaymentStatus::cases())->toHaveCount(3);
});
