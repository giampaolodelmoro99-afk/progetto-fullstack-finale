<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
    actingAsAdmin();
});

/**
 * Orders of September 2026 and around it, with totals free of shipping:
 * paid on the 10th (100,00 €) and on the 1st at 00:30 (120,00 €), shipped
 * (200,00 €), delivered (300,00 €), cancelled after the payment (500,00 €),
 * a rejected attempt and a pending order (400,00 € each), and an order paid
 * in August and still paid (150,00 €).
 */
function septemberOrders(): void
{
    Order::factory()->paid()->create(['created_at' => '2026-09-10 10:00', 'subtotal' => 10000]);
    Order::factory()->paid()->create(['created_at' => '2026-09-01 00:29:00', 'subtotal' => 12000]);
    Order::factory()->shipped()->create(['created_at' => '2026-09-05 10:00', 'subtotal' => 20000]);
    Order::factory()->delivered()->create(['created_at' => '2026-09-02 10:00', 'subtotal' => 30000]);
    Order::factory()->cancelledAfterPayment()->create(['created_at' => '2026-09-03 10:00', 'subtotal' => 50000]);
    Order::factory()->failedPayment()->create(['created_at' => '2026-09-12 10:00', 'subtotal' => 40000]);
    Order::factory()->pending()->create(['created_at' => '2026-09-14 10:00', 'subtotal' => 40000]);
    Order::factory()->paid()->create(['created_at' => '2026-08-20 10:00', 'subtotal' => 15000]);
}

it('counts only the orders paid in the current month', function () {
    septemberOrders();

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.period', ['month' => '2026-09'])
        ->assertJsonPath('data.revenue_month', 72000)
        ->assertJsonPath('data.orders_month', 5)
        ->assertJsonPath('data.orders_to_ship', 3)
        ->assertJsonPath('data.orders_by_status', [
            ['status' => ['value' => 'paid', 'label' => 'Pagato'], 'count' => 2],
            ['status' => ['value' => 'shipped', 'label' => 'Spedito'], 'count' => 1],
            ['status' => ['value' => 'delivered', 'label' => 'Consegnato'], 'count' => 1],
            ['status' => ['value' => 'cancelled', 'label' => 'Annullato'], 'count' => 1],
        ]);
});

it('gives the shape of the dashboard', function () {
    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => [
                'period' => ['month'],
                'revenue_month',
                'orders_month',
                'orders_to_ship',
                'low_stock_count',
                'orders_by_status' => ['*' => ['status' => ['value', 'label'], 'count']],
                'latest_orders',
                'low_stock_products',
            ],
        ]);
});

it('gives zeros and empty lists without orders and products', function () {
    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.revenue_month', 0)
        ->assertJsonPath('data.orders_month', 0)
        ->assertJsonPath('data.orders_to_ship', 0)
        ->assertJsonPath('data.low_stock_count', 0)
        ->assertJsonPath('data.orders_by_status', [
            ['status' => ['value' => 'paid', 'label' => 'Pagato'], 'count' => 0],
            ['status' => ['value' => 'shipped', 'label' => 'Spedito'], 'count' => 0],
            ['status' => ['value' => 'delivered', 'label' => 'Consegnato'], 'count' => 0],
            ['status' => ['value' => 'cancelled', 'label' => 'Annullato'], 'count' => 0],
        ])
        ->assertJsonPath('data.latest_orders', [])
        ->assertJsonPath('data.low_stock_products', []);
});

it('excludes the rejected attempts and the pending orders from every figure', function () {
    Order::factory()->failedPayment()->create(['created_at' => '2026-09-12 10:00']);
    Order::factory()->pending()->create(['created_at' => '2026-09-14 10:00']);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.revenue_month', 0)
        ->assertJsonPath('data.orders_month', 0)
        ->assertJsonPath('data.orders_to_ship', 0)
        ->assertJsonPath('data.latest_orders', []);
});

/*
| The first order is paid on 30 September at 23:20, the second on
| 1 September at 00:00, one minute after its creation.
*/
it('takes the month in italian time at the edges', function (string $now, string $month, int $ordersMonth) {
    Order::factory()->paid()->create(['created_at' => '2026-09-30 23:19:00', 'subtotal' => 10000]);
    Order::factory()->paid()->create(['created_at' => '2026-08-31 23:59:00', 'subtotal' => 10000]);
    $this->travelTo($now);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.period.month', $month)
        ->assertJsonPath('data.orders_month', $ordersMonth)
        ->assertJsonPath('data.revenue_month', $ordersMonth * 10000);
})->with([
    'last day of september at 23:30' => ['2026-09-30 23:30:00', '2026-09', 2],
    'first day of october at 00:10' => ['2026-10-01 00:10:00', '2026-10', 0],
    'last day of august at 23:59:30' => ['2026-08-31 23:59:30', '2026-08', 0],
]);

it('lists the 5 latest orders paid at least once, newest first', function () {
    $orders = collect(range(1, 6))->map(fn (int $day) => Order::factory()->paid()->create([
        'created_at' => sprintf('2026-09-%02d 10:00', $day),
    ]));
    Order::factory()->failedPayment()->create(['created_at' => '2026-09-14 10:00']);
    Order::factory()->pending()->create(['created_at' => '2026-09-14 11:00']);

    $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();

    expect(collect($response->json('data.latest_orders'))->pluck('id')->all())
        ->toBe([$orders[5]->id, $orders[4]->id, $orders[3]->id, $orders[2]->id, $orders[1]->id]);
});

it('gives the shape of a latest order', function () {
    $customer = User::factory()->customer()->create(['name' => 'Marco Bellini', 'email' => 'marco.bellini@esempio.it']);
    $order = Order::factory()->shipped()->for($customer)->create(['created_at' => '2026-09-10 16:52:00', 'subtotal' => 69800]);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.latest_orders', [[
            'id' => $order->id,
            'number' => sprintf('ORD-2026-%06d', $order->id),
            'customer' => ['name' => 'Marco Bellini', 'email' => 'marco.bellini@esempio.it'],
            'created_at' => '2026-09-10T16:52:00+02:00',
            'total' => 69800,
            'status' => ['value' => 'shipped', 'label' => 'Spedito'],
        ]]);
});

it('shows an anonymized customer as "Cliente eliminato" in the latest orders', function () {
    Order::factory()->delivered()->for(User::factory()->anonymized())->create(['created_at' => '2026-09-02 10:00']);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.latest_orders.0.customer', ['name' => 'Cliente eliminato', 'email' => null]);
});

it('counts the low stock products with the admin threshold', function (array $attributes, ?string $state, int $count) {
    $factory = Product::factory();
    ($state === null ? $factory : $factory->{$state}())->create($attributes);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.low_stock_count', $count)
        ->assertJsonCount($count, 'data.low_stock_products');
})->with([
    'out of stock' => [['stock' => 0], null, 1],
    'stock 5' => [['stock' => 5], null, 1],
    'stock 6' => [['stock' => 6], null, 0],
    'deactivated' => [['stock' => 1], 'inactive', 0],
    'deleted' => [['stock' => 1], 'trashed', 0],
]);

it('lists at most 5 low stock products by stock and then name', function () {
    foreach ([
        ['Radio Onda', 3],
        ['Lampada Faro', 0],
        ['Diffusore Eco', 0],
        ['Cavo Seta USB-C 1 m', 2],
        ['Auricolari Sussurro', 5],
        ['Orologio Meridiana', 4],
        ['Supporto Colle in alluminio', 1],
        ['Cuffie Aria', 20],
    ] as [$name, $stock]) {
        Product::factory()->create(['name' => $name, 'stock' => $stock]);
    }

    $response = $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.low_stock_count', 7);

    expect(collect($response->json('data.low_stock_products'))->pluck('name')->all())->toBe([
        'Diffusore Eco',
        'Lampada Faro',
        'Supporto Colle in alluminio',
        'Cavo Seta USB-C 1 m',
        'Radio Onda',
    ]);
});

it('gives the shape of a low stock product with stock, stock status and cover', function () {
    $radio = Product::factory()->create(['name' => 'Radio Onda Portatile', 'stock' => 0]);
    ProductImage::factory()->for($radio)->create(['path' => 'products/radio.webp', 'alt' => 'Radio Onda Portatile', 'position' => 1]);
    $lamp = Product::factory()->create(['name' => 'Lampada Faro', 'stock' => 3]);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.low_stock_products', [
            [
                'id' => $radio->id,
                'name' => 'Radio Onda Portatile',
                'stock' => 0,
                'stock_status' => ['status' => 'out_of_stock', 'label' => 'Esaurito'],
                'cover' => ['url' => 'http://localhost:8000/storage/products/radio.webp', 'alt' => 'Radio Onda Portatile'],
            ],
            [
                'id' => $lamp->id,
                'name' => 'Lampada Faro',
                'stock' => 3,
                'stock_status' => ['status' => 'low_stock', 'label' => 'Ultimi 3 pezzi'],
                'cover' => null,
            ],
        ]);
});
