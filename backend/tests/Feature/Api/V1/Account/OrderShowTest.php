<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductImage;
use App\Models\User;

it('shows an own paid order with rows, payment and history', function () {
    $user = actingAsVerifiedCustomer();
    $product = shopProduct(['name' => 'Cuffie Aria', 'sku' => 'CDS-AUD-001', 'price' => 34900]);
    ProductImage::factory()->for($product)->create(['path' => 'products/cuffie-aria.webp', 'alt' => 'Cuffie Aria di lato', 'position' => 1]);
    $order = Order::factory()
        ->paid()
        ->for($user)
        ->has(OrderItem::factory()->for($product)->state(['quantity' => 1]), 'items')
        ->create([
            'created_at' => '2026-09-28 18:41:00',
            'shipping_address' => [
                'full_name' => 'Giulia Ferri',
                'street' => 'Via San Martino 42',
                'postal_code' => '56125',
                'city' => 'Pisa',
                'province' => 'PI',
                'country' => 'IT',
                'phone' => '+39 333 123 4567',
            ],
        ]);

    $response = fromFrontend()->getJson("/api/v1/orders/{$order->id}")->assertOk();

    expect($response->json('data'))->toMatchArray([
        'id' => $order->id,
        'status' => ['value' => 'paid', 'label' => 'Pagato'],
        'subtotal' => 34900,
        'shipping_cost' => 0,
        'total' => 34900,
        'items_count' => 1,
        'can_cancel' => true,
        'shipping_address' => [
            'full_name' => 'Giulia Ferri',
            'street' => 'Via San Martino 42',
            'postal_code' => '56125',
            'city' => 'Pisa',
            'province' => 'PI',
            'country' => 'IT',
            'phone' => '+39 333 123 4567',
        ],
        'payment' => ['card_last_four' => '4242'],
        'items' => [
            ['product_name' => 'Cuffie Aria', 'product_sku' => 'CDS-AUD-001', 'unit_price' => 34900, 'quantity' => 1, 'line_total' => 34900, 'cover' => ['url' => 'http://localhost:8000/storage/products/cuffie-aria.webp', 'alt' => 'Cuffie Aria di lato']],
        ],
        'status_history' => [
            ['status' => ['value' => 'pending', 'label' => 'In attesa'], 'description' => 'Ordine ricevuto, in attesa del pagamento', 'created_at' => '2026-09-28T18:41:00+02:00'],
            ['status' => ['value' => 'paid', 'label' => 'Pagato'], 'description' => 'Pagamento con carta riuscito', 'created_at' => '2026-09-28T18:42:00+02:00'],
        ],
    ]);
    $response->assertJsonMissingPath('data.status_history.0.changed_by')
        ->assertJsonMissingPath('data.status_history.0.reason')
        ->assertJsonMissingPath('data.payment.status');
});

it('shows the full history without authors', function () {
    $user = actingAsVerifiedCustomer();
    $admin = User::factory()->admin()->create(['name' => 'Marta Lenzi']);
    $order = Order::factory()->delivered($admin)->for($user)->create();

    $response = fromFrontend()->getJson("/api/v1/orders/{$order->id}")->assertOk();

    expect(collect($response->json('data.status_history'))->pluck('description')->all())->toBe([
        'Ordine ricevuto, in attesa del pagamento',
        'Pagamento con carta riuscito',
        'Segnato come spedito',
        'Segnato come consegnato',
    ]);
    $response->assertJsonMissingPath('data.status_history.2.changed_by');
});

it('shows an order cancelled after the payment, with the refunded card', function () {
    $user = actingAsVerifiedCustomer();
    $order = Order::factory()->cancelledAfterPayment()->for($user)->create();

    fromFrontend()->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.status', ['value' => 'cancelled', 'label' => 'Annullato'])
        ->assertJsonPath('data.can_cancel', false)
        ->assertJsonPath('data.payment', ['card_last_four' => '4242'])
        ->assertJsonPath('data.status_history.2.description', 'Annullato e rimborsato');
});

it('allows the cancellation only of a paid order', function (string $state, bool $canCancel) {
    $user = actingAsVerifiedCustomer();
    $order = Order::factory()->{$state}()->for($user)->create();

    fromFrontend()->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.can_cancel', $canCancel);
})->with([
    'paid' => ['paid', true],
    'shipped' => ['shipped', false],
    'delivered' => ['delivered', false],
    'cancelled after payment' => ['cancelledAfterPayment', false],
]);

it('shows no cover for a deleted product or a row without product', function (Closure $product) {
    $user = actingAsVerifiedCustomer();
    $order = Order::factory()
        ->paid()
        ->for($user)
        ->has(OrderItem::factory()->state(fn () => ['product_id' => $product()?->id, 'product_name' => 'Radio Onda', 'product_sku' => 'CDS-AUD-020', 'unit_price' => 7900]), 'items')
        ->create();

    fromFrontend()->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.cover', null)
        ->assertJsonPath('data.items.0.product_name', 'Radio Onda');
})->with([
    'soft deleted' => [function () {
        $product = shopProduct();
        ProductImage::factory()->for($product)->create();
        $product->delete();

        return $product;
    }],
    'product_id null' => [fn () => null],
]);

it('hides the orders the customer cannot see', function (Closure $order) {
    actingAsVerifiedCustomer();

    fromFrontend()->getJson('/api/v1/orders/'.$order()->id)
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);
})->with([
    'failed payment' => [fn () => Order::factory()->failedPayment()->for(auth()->user())->create()],
    'pending' => [fn () => Order::factory()->pending()->for(auth()->user())->create()],
    'of another customer' => [fn () => Order::factory()->paid()->for(User::factory()->customer())->create()],
]);

it('answers 404 for a missing order', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->getJson('/api/v1/orders/999999')->assertNotFound();
});

it('rejects a guest', function () {
    $order = Order::factory()->paid()->create();

    fromFrontend()->getJson("/api/v1/orders/{$order->id}")->assertUnauthorized();
});

it('rejects an administrator on the customer route', function () {
    actingAsAdmin();
    $order = Order::factory()->paid()->create();

    fromFrontend()->getJson("/api/v1/orders/{$order->id}")->assertForbidden();
});
