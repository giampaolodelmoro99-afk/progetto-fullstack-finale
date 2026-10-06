<?php

use App\Models\Order;

/*
| The link of each status in the dashboard opens the order list filtered by
| status and paid_month of the month shown: the two numbers must match.
*/

/**
 * In September 2026, italian time: two paid, one of them paid on the 1st at
 * 00:00:00; one shipped; two delivered, one of them paid on the 30th at
 * 23:59:59; one cancelled after the payment. Outside the month: paid on
 * 31 August at 23:59:59 and still paid, paid on 1 October at 00:00:00, a
 * rejected attempt and a pending order.
 */
function consistencyOrders(): void
{
    Order::factory()->paid()->create(['created_at' => '2026-09-10 10:00']);
    Order::factory()->paid()->create(['created_at' => '2026-08-31 23:59:00']);
    Order::factory()->shipped()->create(['created_at' => '2026-09-05 10:00']);
    Order::factory()->delivered()->create(['created_at' => '2026-09-02 10:00']);
    Order::factory()->delivered()->create(['created_at' => '2026-09-30 23:58:59']);
    Order::factory()->cancelledAfterPayment()->create(['created_at' => '2026-09-03 10:00']);
    Order::factory()->paid()->create(['created_at' => '2026-08-31 23:58:59']);
    Order::factory()->delivered()->create(['created_at' => '2026-09-30 23:59:00']);
    Order::factory()->failedPayment()->create(['created_at' => '2026-09-12 10:00']);
    Order::factory()->pending()->create(['created_at' => '2026-09-14 10:00']);
}

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
    actingAsAdmin();
    consistencyOrders();
});

it('counts each status of the month as the list filtered by status and month', function () {
    $dashboard = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

    foreach ($dashboard['orders_by_status'] as $row) {
        $total = $this->getJson("/api/v1/admin/orders?status={$row['status']['value']}&paid_month={$dashboard['period']['month']}")
            ->assertOk()
            ->json('meta.total');

        expect($total)->toBe($row['count'], "status {$row['status']['value']}");
    }

    expect(array_sum(array_column($dashboard['orders_by_status'], 'count')))->toBe($dashboard['orders_month']);
});

it('gives the expected counts of the month', function () {
    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.period.month', '2026-09')
        ->assertJsonPath('data.orders_month', 6)
        ->assertJsonPath('data.orders_to_ship', 3)
        ->assertJsonPath('data.orders_by_status', [
            ['status' => ['value' => 'paid', 'label' => 'Pagato'], 'count' => 2],
            ['status' => ['value' => 'shipped', 'label' => 'Spedito'], 'count' => 1],
            ['status' => ['value' => 'delivered', 'label' => 'Consegnato'], 'count' => 2],
            ['status' => ['value' => 'cancelled', 'label' => 'Annullato'], 'count' => 1],
        ]);

    $this->getJson('/api/v1/admin/orders?paid_month=2026-09')->assertOk()->assertJsonPath('meta.total', 6);
});

it('finds the orders outside the month in the months around it', function () {
    $this->getJson('/api/v1/admin/orders?paid_month=2026-08')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/admin/orders?paid_month=2026-10')->assertOk()->assertJsonPath('meta.total', 1);
});
