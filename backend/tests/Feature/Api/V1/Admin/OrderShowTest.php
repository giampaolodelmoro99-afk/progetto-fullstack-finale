<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductImage;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-09-30 12:00');
});

function giuliaAddress(): array
{
    return [
        'full_name' => 'Giulia Ferri',
        'street' => 'Via San Martino 42',
        'postal_code' => '56125',
        'city' => 'Pisa',
        'province' => 'PI',
        'country' => 'IT',
        'phone' => '+39 333 123 4567',
    ];
}

it('shows a shipped order with customer, payment, rows and history with the authors', function () {
    $admin = User::factory()->admin()->create(['name' => 'Marta Lenzi']);
    $this->actingAs($admin, 'web');
    $customer = User::factory()->customer()->create(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it']);
    $product = shopProduct(['name' => 'Cuffie Aria', 'sku' => 'CDS-AUD-001', 'price' => 34900]);
    ProductImage::factory()->for($product)->create(['path' => 'products/cuffie-aria.webp', 'alt' => 'Cuffie Aria di lato', 'position' => 1]);
    $order = Order::factory()
        ->shipped($admin)
        ->for($customer)
        ->has(OrderItem::factory()->for($product)->state(['quantity' => 2]), 'items')
        ->create(['created_at' => '2026-09-28 18:41:00', 'shipping_address' => giuliaAddress()]);

    $response = $this->getJson("/api/v1/admin/orders/{$order->id}")->assertOk();

    expect(array_keys($response->json('data')))->toBe([
        'id', 'number', 'customer', 'created_at', 'items_count', 'total', 'status', 'failed_payment',
        'paid_at', 'subtotal', 'shipping_cost', 'can_cancel', 'allowed_transitions', 'shipping_address',
        'payment', 'items', 'status_history',
    ])->and($response->json('data'))->toEqual([
        'id' => $order->id,
        'number' => sprintf('ORD-2026-%06d', $order->id),
        'customer' => ['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it'],
        'created_at' => '2026-09-28T18:41:00+02:00',
        'items_count' => 2,
        'total' => 69800,
        'status' => ['value' => 'shipped', 'label' => 'Spedito'],
        'failed_payment' => false,
        'paid_at' => '2026-09-28T18:42:00+02:00',
        'subtotal' => 69800,
        'shipping_cost' => 0,
        'can_cancel' => false,
        'allowed_transitions' => ['delivered'],
        'shipping_address' => giuliaAddress(),
        'payment' => [
            'card_last_four' => '4242',
            'status' => ['value' => 'succeeded', 'label' => 'Riuscito'],
            'amount' => 69800,
            'created_at' => '2026-09-28T18:42:00+02:00',
        ],
        'items' => [
            ['product_name' => 'Cuffie Aria', 'product_sku' => 'CDS-AUD-001', 'unit_price' => 34900, 'quantity' => 2, 'line_total' => 69800, 'cover' => ['url' => 'http://localhost:8000/storage/products/cuffie-aria.webp', 'alt' => 'Cuffie Aria di lato']],
        ],
        'status_history' => [
            ['status' => ['value' => 'pending', 'label' => 'In attesa'], 'description' => 'Ordine ricevuto, in attesa del pagamento', 'changed_by' => null, 'reason' => null, 'created_at' => '2026-09-28T18:41:00+02:00'],
            ['status' => ['value' => 'paid', 'label' => 'Pagato'], 'description' => 'Pagamento con carta riuscito', 'changed_by' => null, 'reason' => null, 'created_at' => '2026-09-28T18:42:00+02:00'],
            ['status' => ['value' => 'shipped', 'label' => 'Spedito'], 'description' => 'Segnato come spedito da Marta Lenzi', 'changed_by' => ['id' => $admin->id, 'name' => 'Marta Lenzi'], 'reason' => null, 'created_at' => '2026-09-29T18:42:00+02:00'],
        ],
    ]);
});

it('keeps the phone in the address snapshot, not in the customer', function () {
    actingAsAdmin();
    $order = Order::factory()->paid()->create(['shipping_address' => giuliaAddress()]);

    $this->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.shipping_address.phone', '+39 333 123 4567')
        ->assertJsonMissingPath('data.customer.phone');
});

it('shows a rejected attempt with the failed payment and the reason', function () {
    actingAsAdmin();
    $order = Order::factory()->failedPayment()->create(['created_at' => '2026-09-28 18:41:00', 'subtotal' => 4900]);

    $response = $this->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.status', ['value' => 'cancelled', 'label' => 'Annullato'])
        ->assertJsonPath('data.failed_payment', true)
        ->assertJsonPath('data.paid_at', null)
        ->assertJsonPath('data.can_cancel', false)
        ->assertJsonPath('data.allowed_transitions', [])
        ->assertJsonPath('data.payment', [
            'card_last_four' => '0002',
            'status' => ['value' => 'failed', 'label' => 'Non riuscito'],
            'amount' => 5590,
            'created_at' => '2026-09-28T18:42:00+02:00',
        ]);

    expect($response->json('data.status_history.1'))->toBe([
        'status' => ['value' => 'cancelled', 'label' => 'Annullato'],
        'description' => 'Pagamento non riuscito: Carta rifiutata dalla banca',
        'changed_by' => null,
        'reason' => 'Carta rifiutata dalla banca',
        'created_at' => '2026-09-28T18:42:00+02:00',
    ]);
});

it('shows a null payment when the gateway never saw the card', function () {
    actingAsAdmin();
    $order = Order::factory()->pending()->create();

    $this->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.payment', null)
        ->assertJsonPath('data.allowed_transitions', []);
});

it('shows the refunded payment of an order cancelled after the payment', function () {
    actingAsAdmin();
    $order = Order::factory()->cancelledAfterPayment()->create();

    $this->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.payment.status', ['value' => 'refunded', 'label' => 'Rimborsato'])
        ->assertJsonPath('data.failed_payment', false);
});

it('gives the transitions the admin can make now', function (string $state, array $transitions) {
    actingAsAdmin();
    $order = Order::factory()->{$state}()->create();

    $this->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.allowed_transitions', $transitions);
})->with([
    'pending' => ['pending', []],
    'paid' => ['paid', ['shipped', 'cancelled']],
    'shipped' => ['shipped', ['delivered']],
    'delivered' => ['delivered', []],
    'cancelled after payment' => ['cancelledAfterPayment', []],
    'failed payment' => ['failedPayment', []],
]);

it('shows an anonymized customer as "Cliente eliminato", also as author of the cancellation', function () {
    actingAsAdmin();
    $customer = User::factory()->anonymized()->create();
    $order = Order::factory()->cancelledAfterPayment()->for($customer)->create(['shipping_address' => giuliaAddress()]);

    $this->getJson("/api/v1/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.customer', ['name' => 'Cliente eliminato', 'email' => null])
        ->assertJsonPath('data.shipping_address.full_name', 'Giulia Ferri')
        ->assertJsonPath('data.status_history.2.description', 'Annullato e rimborsato da Cliente eliminato')
        ->assertJsonPath('data.status_history.2.changed_by', ['id' => $customer->id, 'name' => 'Cliente eliminato']);
});

it('answers 404 for an order that does not exist', function () {
    actingAsAdmin();

    $this->getJson('/api/v1/admin/orders/999999')->assertNotFound();
});
