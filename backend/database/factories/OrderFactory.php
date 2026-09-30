<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Every order gets its number, at least one item with coherent totals and the
 * creation row of the history (null → pending). Each status state adds the
 * matching timestamps, history rows and payment; rows and payment are written
 * once, so states can also be chained (paid()->shipped()).
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'number' => null,
            'status' => OrderStatus::Pending,
            'subtotal' => fake()->numberBetween(19, 299) * 100 + 90,
            'shipping_cost' => fn (array $attributes) => $attributes['subtotal'] >= config('shop.free_shipping_threshold')
                ? 0
                : config('shop.shipping_cost'),
            'total' => fn (array $attributes) => $attributes['subtotal'] + $attributes['shipping_cost'],
            'shipping_address' => fn () => [
                'full_name' => fake()->name(),
                'street' => 'Via '.fake()->lastName().' '.fake()->numberBetween(1, 200),
                'postal_code' => fake()->numerify('#####'),
                'city' => fake()->city(),
                'province' => fake()->randomElement(['MI', 'RM', 'TO', 'FI', 'PI', 'BO']),
                'country' => 'IT',
                'phone' => '+39 3'.fake()->numerify('## ### ####'),
            ],
            'created_at' => now(),
            'terms_accepted_at' => fn (array $attributes) => self::date($attributes['created_at']),
            'paid_at' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            if ($order->number === null) {
                $order->update(['number' => Order::formatNumber($order->id, $order->created_at)]);
            }

            $this->syncItemsAndTotals($order);

            $this->recordChange($order, null, OrderStatus::Pending, $order->created_at);
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Pending,
        ]);
    }

    /**
     * Paid one minute after creation, with a succeeded payment.
     */
    public function paid(): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'status' => OrderStatus::Paid,
                'paid_at' => fn (array $attributes) => self::date($attributes['created_at'])->addMinute(),
            ])
            ->afterCreating(function (Order $order) {
                $this->recordPayment($order, [
                    'provider_reference' => 'fake_'.Str::random(24),
                    'amount' => $order->total,
                    'status' => PaymentStatus::Succeeded,
                    'card_last_four' => '4242',
                    'created_at' => $order->paid_at,
                ]);

                $this->recordChange($order, OrderStatus::Pending, OrderStatus::Paid, $order->paid_at);
            });
    }

    /**
     * Shipped one day after payment by an admin (a new one when not given).
     */
    public function shipped(?User $admin = null): static
    {
        return $this->paid()
            ->state(fn (array $attributes) => [
                'status' => OrderStatus::Shipped,
                'shipped_at' => fn (array $attributes) => self::date($attributes['paid_at'])->addDay(),
            ])
            ->afterCreating(function (Order $order) use ($admin) {
                $this->recordChange(
                    $order,
                    OrderStatus::Paid,
                    OrderStatus::Shipped,
                    $order->shipped_at,
                    fn () => $admin ?? User::factory()->admin()->create(),
                );
            });
    }

    /**
     * Delivered two days after shipping, by the same admin who shipped it.
     */
    public function delivered(?User $admin = null): static
    {
        return $this->shipped($admin)
            ->state(fn (array $attributes) => [
                'status' => OrderStatus::Delivered,
                'delivered_at' => fn (array $attributes) => self::date($attributes['shipped_at'])->addDays(2),
            ])
            ->afterCreating(function (Order $order) {
                $this->recordChange(
                    $order,
                    OrderStatus::Shipped,
                    OrderStatus::Delivered,
                    $order->delivered_at,
                    fn () => User::find($order->statusChanges()
                        ->where('to_status', OrderStatus::Shipped)
                        ->value('changed_by')),
                );
            });
    }

    /**
     * Paid, then cancelled one day later with a refund, by the customer when no author is given.
     */
    public function cancelledAfterPayment(?User $by = null): static
    {
        return $this->paid()
            ->state(fn (array $attributes) => [
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => fn (array $attributes) => self::date($attributes['paid_at'])->addDay(),
            ])
            ->afterCreating(function (Order $order) use ($by) {
                $order->payments()->update([
                    'status' => PaymentStatus::Refunded,
                    'updated_at' => $order->cancelled_at,
                ]);

                $this->recordChange(
                    $order,
                    OrderStatus::Paid,
                    OrderStatus::Cancelled,
                    $order->cancelled_at,
                    fn () => $by ?? User::find($order->user_id),
                );
            });
    }

    /**
     * Payment rejected by the gateway: cancelled without paid_at, visible only to the admin.
     */
    public function failedPayment(string $reason = 'Carta rifiutata dalla banca'): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'status' => OrderStatus::Cancelled,
                'paid_at' => null,
                'cancelled_at' => fn (array $attributes) => self::date($attributes['created_at'])->addMinute(),
            ])
            ->afterCreating(function (Order $order) use ($reason) {
                $this->recordPayment($order, [
                    'provider_reference' => null,
                    'amount' => $order->total,
                    'status' => PaymentStatus::Failed,
                    'card_last_four' => '0002',
                    'created_at' => $order->cancelled_at,
                ]);

                $this->recordChange(
                    $order,
                    OrderStatus::Pending,
                    OrderStatus::Cancelled,
                    $order->cancelled_at,
                    null,
                    $reason,
                );
            });
    }

    /**
     * Without items, one item worth the whole subtotal is created; with items
     * attached through has(), the totals are recalculated from them.
     */
    private function syncItemsAndTotals(Order $order): void
    {
        if ($order->items()->doesntExist()) {
            OrderItem::factory()->for($order)->create([
                'unit_price' => $order->subtotal,
                'quantity' => 1,
            ]);

            return;
        }

        $subtotal = (int) $order->items()->sum('line_total');
        $shippingCost = $subtotal >= config('shop.free_shipping_threshold') ? 0 : config('shop.shipping_cost');

        $order->update([
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total' => $subtotal + $shippingCost,
        ]);
    }

    /**
     * Writes the history row once: states that include paid() can be chained
     * (e.g. paid()->shipped()) without duplicating rows. The author is resolved
     * only when the row is written, so no extra admin is created.
     *
     * @param  (Closure(): ?User)|null  $by
     */
    private function recordChange(
        Order $order,
        ?OrderStatus $from,
        OrderStatus $to,
        DateTimeInterface $at,
        ?Closure $by = null,
        ?string $reason = null,
    ): void {
        $exists = $order->statusChanges()
            ->where('from_status', $from)
            ->where('to_status', $to)
            ->exists();

        if ($exists) {
            return;
        }

        $order->statusChanges()->forceCreate([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $by === null ? null : $by()?->id,
            'reason' => $reason,
            'created_at' => $at,
        ]);
    }

    /**
     * Writes the single payment of the order once.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function recordPayment(Order $order, array $attributes): void
    {
        if ($order->payments()->exists()) {
            return;
        }

        $order->payments()->forceCreate([
            'provider' => 'fake',
            ...$attributes,
            'updated_at' => $attributes['created_at'],
        ]);
    }

    private static function date(DateTimeInterface|string $value): Carbon
    {
        return $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);
    }
}
