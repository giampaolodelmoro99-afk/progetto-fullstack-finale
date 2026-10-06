<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo('2026-09-15 12:00');
    actingAsAdmin();
});

function responseNumbers(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('number')->all();
}

it('lists every order newest first, rejected attempts and pending orders included', function () {
    $paid = Order::factory()->paid()->create(['created_at' => '2026-09-10 10:00']);
    $failed = Order::factory()->failedPayment()->create(['created_at' => '2026-09-12 10:00']);
    $pending = Order::factory()->pending()->create(['created_at' => '2026-09-14 10:00']);
    $delivered = Order::factory()->delivered()->create(['created_at' => '2026-08-01 10:00']);

    $response = $this->getJson('/api/v1/admin/orders')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$pending->id, $failed->id, $paid->id, $delivered->id])
        ->and($response->json('meta.total'))->toBe(4)
        ->and($response->json('meta.per_page'))->toBe(10);
});

it('orders the rows created at the same time by id, newest first', function () {
    $first = Order::factory()->paid()->create(['created_at' => '2026-09-10 10:00']);
    $second = Order::factory()->paid()->create(['created_at' => '2026-09-10 10:00']);

    $response = $this->getJson('/api/v1/admin/orders')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('gives the shape of a row with the customer, the item count and failed_payment', function () {
    $customer = User::factory()->customer()->create(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it']);
    $order = Order::factory()
        ->paid()
        ->for($customer)
        ->has(OrderItem::factory()->state(['unit_price' => 34900, 'quantity' => 1]), 'items')
        ->has(OrderItem::factory()->state(['unit_price' => 5900, 'quantity' => 3]), 'items')
        ->create(['created_at' => '2026-09-10 18:41:00']);

    $this->getJson('/api/v1/admin/orders')
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => [['id', 'number', 'customer' => ['name', 'email'], 'created_at', 'items_count', 'total', 'status' => ['value', 'label'], 'failed_payment']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
        ])
        ->assertJsonPath('data.0', [
            'id' => $order->id,
            'number' => sprintf('ORD-2026-%06d', $order->id),
            'customer' => ['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it'],
            'created_at' => '2026-09-10T18:41:00+02:00',
            'items_count' => 4,
            'total' => 52600,
            'status' => ['value' => 'paid', 'label' => 'Pagato'],
            'failed_payment' => false,
        ]);
});

it('marks the rejected attempts with failed_payment', function () {
    Order::factory()->failedPayment()->create(['created_at' => '2026-09-10 10:00']);
    Order::factory()->cancelledAfterPayment()->create(['created_at' => '2026-09-09 10:00']);

    $response = $this->getJson('/api/v1/admin/orders')->assertOk();

    expect(collect($response->json('data'))->pluck('failed_payment')->all())->toBe([true, false]);
});

it('searches by part of the order number and by customer email', function () {
    $giulia = User::factory()->customer()->create(['email' => 'giulia.ferri@esempio.it']);
    $byEmail = Order::factory()->paid()->for($giulia)->create();
    $other = Order::factory()->paid()->create();

    expect(responseNumbers($this->getJson('/api/v1/admin/orders?search=giulia.ferri')))->toBe([$byEmail->number])
        ->and(responseNumbers($this->getJson('/api/v1/admin/orders?search='.substr($other->number, -6))))->toBe([$other->number]);
});

it('filters by status', function () {
    $shipped = Order::factory()->shipped()->create();
    Order::factory()->paid()->create();
    Order::factory()->failedPayment()->create();

    expect(responseNumbers($this->getJson('/api/v1/admin/orders?status=shipped')))->toBe([$shipped->number]);
});

it('filters the rejected payment attempts', function () {
    $failed = Order::factory()->failedPayment()->create();
    Order::factory()->cancelledAfterPayment()->create();
    Order::factory()->paid()->create();
    Order::factory()->pending()->create();

    expect(responseNumbers($this->getJson('/api/v1/admin/orders?failed_payment=1')))->toBe([$failed->number]);
});

it('filters by month of payment in italian time', function (string $paidAt, bool $included) {
    $order = Order::factory()->paid()->create(['created_at' => '2026-08-20 10:00', 'paid_at' => $paidAt]);

    expect(responseNumbers($this->getJson('/api/v1/admin/orders?paid_month=2026-09')))
        ->toBe($included ? [$order->number] : []);
})->with([
    'first day at 00:30' => ['2026-09-01 00:30:00', true],
    'last day at 23:59:59' => ['2026-09-30 23:59:59', true],
    'previous month at 23:30' => ['2026-08-31 23:30:00', false],
    'next month at 00:00' => ['2026-10-01 00:00:00', false],
]);

it('never finds rejected attempts and pending orders by month of payment', function () {
    Order::factory()->failedPayment()->create();
    Order::factory()->pending()->create();

    $this->getJson('/api/v1/admin/orders?paid_month=2026-09')->assertOk()->assertJsonPath('meta.total', 0);
});

it('accepts a month on the 31st of the following month', function () {
    $this->travelTo('2026-10-31 12:00');
    $order = Order::factory()->paid()->create(['created_at' => '2026-09-20 10:00']);

    expect(responseNumbers($this->getJson('/api/v1/admin/orders?paid_month=2026-09')->assertOk()))->toBe([$order->number]);
});

it('returns nothing when the filters exclude each other', function (string $query) {
    Order::factory()->paid()->create();
    Order::factory()->failedPayment()->create();

    $this->getJson("/api/v1/admin/orders?{$query}")->assertOk()->assertJsonPath('meta.total', 0);
})->with([
    'month of payment and failed attempts' => ['paid_month=2026-09&failed_payment=1'],
    'paid and failed attempts' => ['status=paid&failed_payment=1'],
]);

it('combines status and month of payment', function () {
    $shipped = Order::factory()->shipped()->create(['created_at' => '2026-09-02 10:00']);
    Order::factory()->shipped()->create(['created_at' => '2026-08-02 10:00']);
    Order::factory()->paid()->create(['created_at' => '2026-09-03 10:00']);

    expect(responseNumbers($this->getJson('/api/v1/admin/orders?status=shipped&paid_month=2026-09')))->toBe([$shipped->number]);
});

it('rejects invalid filters', function (string $query, string $field) {
    $this->getJson("/api/v1/admin/orders?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'month without the leading zero' => ['paid_month=2026-9', 'paid_month'],
    'month 13' => ['paid_month=2026-13', 'paid_month'],
    'month in words' => ['paid_month=settembre', 'paid_month'],
    'unknown status' => ['status=lost', 'status'],
    'failed_payment 0' => ['failed_payment=0', 'failed_payment'],
    'failed_payment true' => ['failed_payment=true', 'failed_payment'],
    'per_page 7' => ['per_page=7', 'per_page'],
    'page 0' => ['page=0', 'page'],
    'search of 101 characters' => ['search='.str_repeat('a', 101), 'search'],
]);

it('explains the format of the month in italian', function () {
    $this->getJson('/api/v1/admin/orders?paid_month=settembre')
        ->assertUnprocessable()
        ->assertJsonPath('errors.paid_month.0', 'Indica il mese nel formato AAAA-MM, per esempio 2026-09.');
});

it('paginates with 25 rows and keeps the filters in the links', function () {
    Order::factory()->count(27)->paid()->create();

    $response = $this->getJson('/api/v1/admin/orders?per_page=25&status=paid&page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(27)
        ->and($response->json('links.first'))->toContain('status=paid')
        ->and($response->json('links.first'))->toContain('per_page=25');
});

it('shows an anonymized customer as "Cliente eliminato" without email', function () {
    $order = Order::factory()->delivered()->for(User::factory()->anonymized())->create();

    $this->getJson('/api/v1/admin/orders')
        ->assertOk()
        ->assertJsonPath('data.0.id', $order->id)
        ->assertJsonPath('data.0.customer', ['name' => 'Cliente eliminato', 'email' => null]);
});

it('never finds an anonymized customer by the original or the fictitious email', function () {
    $customer = User::factory()->customer()->create(['email' => 'mario.rossi@esempio.it']);
    Order::factory()->delivered()->for($customer)->create();
    $customer->forceFill(['name' => 'Utente eliminato', 'email' => "deleted-{$customer->id}@anonymized.invalid", 'anonymized_at' => now()])->save();

    foreach (['mario.rossi@esempio.it', 'mario', 'anonymized.invalid', "deleted-{$customer->id}"] as $term) {
        $this->getJson('/api/v1/admin/orders?search='.urlencode($term))->assertOk()->assertJsonPath('meta.total', 0);
    }
});

it('lists the orders with the same number of queries for 2 and for 10 orders', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/admin/orders')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    Order::factory()->count(2)->paid()->create();
    $few = $count();
    Order::factory()->count(8)->paid()->create();

    expect($count())->toBe($few);
});
