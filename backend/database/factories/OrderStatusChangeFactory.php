<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Single history rows, for tests that build a history by hand.
 * The OrderFactory states already write a coherent history.
 *
 * @extends Factory<OrderStatusChange>
 */
class OrderStatusChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => OrderStatus::Pending,
            'to_status' => OrderStatus::Paid,
            'changed_by' => null,
            'reason' => null,
            'created_at' => now(),
        ];
    }
}
