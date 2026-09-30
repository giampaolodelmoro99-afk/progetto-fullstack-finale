<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Snapshot of name, SKU and price of the product at the time of purchase.
 * Use it with for($order): the OrderFactory already creates one item per order.
 *
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fn (array $attributes) => self::product($attributes)?->name ?? 'Cuffie Aria',
            'product_sku' => fn (array $attributes) => self::product($attributes)?->sku ?? 'CDS-AUD-001',
            'unit_price' => fn (array $attributes) => self::product($attributes)?->price ?? 34900,
            'quantity' => fake()->numberBetween(1, 3),
            'line_total' => fn (array $attributes) => $attributes['unit_price'] * $attributes['quantity'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function product(array $attributes): ?Product
    {
        return $attributes['product_id'] === null ? null : Product::withTrashed()->find($attributes['product_id']);
    }
}
