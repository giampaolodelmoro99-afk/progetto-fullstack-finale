<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Single payments, for tests that build them by hand.
 * The OrderFactory states already write the matching payment.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'fake',
            'provider_reference' => 'fake_'.Str::random(24),
            'amount' => fn (array $attributes) => Order::find($attributes['order_id'])->total,
            'status' => PaymentStatus::Succeeded,
            'card_last_four' => '4242',
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_reference' => null,
            'status' => PaymentStatus::Failed,
            'card_last_four' => '0002',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Refunded,
        ]);
    }
}
