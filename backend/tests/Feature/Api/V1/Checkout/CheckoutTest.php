<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Support\PaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Support\ScriptedPaymentGateway;

beforeEach(function () {
    $this->travelTo(Carbon\Carbon::parse('2026-09-28 18:42:00', 'Europe/Rome'));
    Notification::fake();
});

/**
 * The cart of the purchase mockup (Design B): 496,00 € with free shipping.
 *
 * @return array{headphones: Product, charger: Product, cable: Product}
 */
function designBCart(User $user): array
{
    $audio = Category::factory()->create(['name' => 'Audio']);
    $charging = Category::factory()->create(['name' => 'Ricarica ed energia']);
    $products = [
        'headphones' => shopProduct(['name' => 'Cuffie Aria', 'sku' => 'CDS-AUD-001', 'price' => 34900, 'stock' => 18, 'category_id' => $audio->id]),
        'charger' => shopProduct(['name' => 'Caricatore Brezza 65 W', 'sku' => 'CDS-RIC-010', 'price' => 5900, 'stock' => 12, 'category_id' => $charging->id]),
        'cable' => shopProduct(['name' => 'Cavo Seta USB-C 1 m', 'sku' => 'CDS-RIC-020', 'price' => 2900, 'stock' => 5, 'category_id' => $charging->id]),
    ];
    cartRow($user, $products['headphones'], 1);
    cartRow($user, $products['charger'], 2);
    cartRow($user, $products['cable'], 1);

    return $products;
}

it('places and pays the order of the purchase mockup', function () {
    [$user, $address] = checkoutCustomer();
    $products = designBCart($user);
    ProductImage::factory()->for($products['headphones'])->create(['path' => 'products/cuffie-aria.webp', 'alt' => 'Cuffie Aria di lato']);

    $response = fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))->assertCreated();

    $order = Order::query()->sole();

    expect($response->json('data'))->toMatchArray([
        'id' => $order->id,
        'number' => sprintf('ORD-2026-%06d', $order->id),
        'status' => ['value' => 'paid', 'label' => 'Pagato'],
        'created_at' => '2026-09-28T18:42:00+02:00',
        'paid_at' => '2026-09-28T18:42:00+02:00',
        'subtotal' => 49600,
        'shipping_cost' => 0,
        'total' => 49600,
        'items_count' => 4,
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
    ])
        ->and($response->json('data.items'))->toBe([
            ['product_name' => 'Cuffie Aria', 'product_sku' => 'CDS-AUD-001', 'unit_price' => 34900, 'quantity' => 1, 'line_total' => 34900, 'cover' => ['url' => 'http://localhost:8000/storage/products/cuffie-aria.webp', 'alt' => 'Cuffie Aria di lato']],
            ['product_name' => 'Caricatore Brezza 65 W', 'product_sku' => 'CDS-RIC-010', 'unit_price' => 5900, 'quantity' => 2, 'line_total' => 11800, 'cover' => null],
            ['product_name' => 'Cavo Seta USB-C 1 m', 'product_sku' => 'CDS-RIC-020', 'unit_price' => 2900, 'quantity' => 1, 'line_total' => 2900, 'cover' => null],
        ])
        ->and($response->json('data.status_history'))->toBe([
            ['status' => ['value' => 'pending', 'label' => 'In attesa'], 'description' => 'Ordine ricevuto, in attesa del pagamento', 'created_at' => '2026-09-28T18:42:00+02:00'],
            ['status' => ['value' => 'paid', 'label' => 'Pagato'], 'description' => 'Pagamento con carta riuscito', 'created_at' => '2026-09-28T18:42:00+02:00'],
        ]);

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->user_id)->toBe($user->id)
        ->and($order->paid_at->toDateTimeString())->toBe('2026-09-28 18:42:00')
        ->and($order->terms_accepted_at->toDateTimeString())->toBe('2026-09-28 18:42:00')
        ->and($products['headphones']->fresh()->stock)->toBe(17)
        ->and($products['charger']->fresh()->stock)->toBe(10)
        ->and($products['cable']->fresh()->stock)->toBe(4)
        ->and(CartItem::query()->count())->toBe(0)
        ->and($user->cart()->exists())->toBeTrue();

    $payment = Payment::query()->sole();
    expect($payment->order_id)->toBe($order->id)
        ->and($payment->provider)->toBe('fake')
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->amount)->toBe(49600)
        ->and($payment->card_last_four)->toBe('4242')
        ->and($payment->provider_reference)->toStartWith('fake_ch_');

    expect($order->statusChanges->map(fn ($change) => [$change->from_status, $change->to_status, $change->changed_by, $change->reason])->all())
        ->toBe([
            [null, OrderStatus::Pending, null, null],
            [OrderStatus::Pending, OrderStatus::Paid, null, null],
        ]);

    Notification::assertSentTo($user, OrderPlacedNotification::class, fn ($notification) => $notification->order->id === $order->id);
    Notification::assertSentTimes(OrderPlacedNotification::class, 1);
});

it('adds the shipping cost below the free shipping threshold', function () {
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(['price' => 2900, 'stock' => 5]), 2);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))
        ->assertCreated()
        ->assertJsonPath('data.subtotal', 5800)
        ->assertJsonPath('data.shipping_cost', 690)
        ->assertJsonPath('data.total', 6490);

    expect(Payment::query()->sole()->amount)->toBe(6490);
});

it('keeps the snapshot of names, prices and address after they change', function () {
    [$user, $address] = checkoutCustomer();
    $product = shopProduct(['name' => 'Cuffie Aria', 'price' => 34900]);
    cartRow($user, $product, 1);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))->assertCreated();

    $product->update(['name' => 'Cuffie Aria 2', 'price' => 39900]);
    $address->update(['street' => 'Via dei Servi 15']);
    $order = Order::query()->sole();

    fromFrontend()->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.product_name', 'Cuffie Aria')
        ->assertJsonPath('data.items.0.unit_price', 34900)
        ->assertJsonPath('data.shipping_address.street', 'Via San Martino 42');
});

it('rejects the declined card, cancels the attempt and gives the stock back', function () {
    [$user, $address] = checkoutCustomer();
    $products = designBCart($user);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id, ['number' => '4000 0000 0000 0002']))
        ->assertStatus(402)
        ->assertExactJson([
            'message' => 'La carta è stata rifiutata e non ti è stato addebitato nulla. Controlla i dati della carta e riprova.',
            'code' => 'payment_failed',
        ]);

    $order = Order::query()->sole();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->paid_at)->toBeNull()
        ->and($order->cancelled_at->toDateTimeString())->toBe('2026-09-28 18:42:00')
        ->and($products['headphones']->fresh()->stock)->toBe(18)
        ->and($products['charger']->fresh()->stock)->toBe(12)
        ->and($products['cable']->fresh()->stock)->toBe(5)
        ->and(CartItem::query()->count())->toBe(3);

    $payment = Payment::query()->sole();
    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->card_last_four)->toBe('0002')
        ->and($payment->amount)->toBe(49600);

    expect($order->statusChanges->map(fn ($change) => [$change->from_status, $change->to_status, $change->changed_by, $change->reason])->all())
        ->toBe([
            [null, OrderStatus::Pending, null, null],
            [OrderStatus::Pending, OrderStatus::Cancelled, null, 'Carta rifiutata dalla banca'],
        ]);

    Notification::assertNothingSent();
});

it('gives the stock back to a product deleted while the gateway was answering', function () {
    [$user, $address] = checkoutCustomer();
    $product = shopProduct(['stock' => 7]);
    cartRow($user, $product, 3);
    useGateway(new ScriptedPaymentGateway(onCharge: function () use ($product) {
        Product::query()->findOrFail($product->id)->delete();

        return PaymentResult::failure('Carta rifiutata dalla banca', '0002');
    }));

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))->assertStatus(402);

    $deleted = Product::withTrashed()->findOrFail($product->id);
    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->stock)->toBe(7);
});

it('refuses a cart that cannot be bought, without creating an order', function (Closure $setup, array $expected, int $stock) {
    [$user, $address] = checkoutCustomer();
    $available = shopProduct(['name' => 'Cuffie Aria', 'stock' => 18]);
    cartRow($user, $available, 1);
    $problem = $setup($user);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))
        ->assertConflict()
        ->assertExactJson([
            'message' => 'La disponibilità di alcuni prodotti è cambiata: aggiorna il carrello per completare l\'ordine.',
            'code' => 'insufficient_stock',
            'products' => [$expected],
        ]);

    expect(Order::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and($available->fresh()->stock)->toBe(18)
        ->and(Product::withTrashed()->findOrFail($problem->id)->stock)->toBe($stock)
        ->and(CartItem::query()->count())->toBe(2);
})->with([
    'out of stock' => [
        function (User $user) {
            $product = shopProduct(['name' => 'Radio Onda Portatile', 'stock' => 0]);
            cartRow($user, $product, 1);

            return $product;
        },
        ['product' => 'Radio Onda Portatile', 'requested' => 1, 'available' => 0],
        0,
    ],
    'deactivated' => [
        function (User $user) {
            $product = shopProduct(['name' => 'Lampada Faro', 'stock' => 9, 'is_active' => false]);
            cartRow($user, $product, 2);

            return $product;
        },
        ['product' => 'Lampada Faro', 'requested' => 2, 'available' => 0],
        9,
    ],
    'deleted' => [
        function (User $user) {
            $product = shopProduct(['name' => 'Diffusore Eco', 'stock' => 9]);
            cartRow($user, $product, 1);
            $product->delete();

            return $product;
        },
        ['product' => 'Diffusore Eco', 'requested' => 1, 'available' => 0],
        9,
    ],
    'above the stock' => [
        function (User $user) {
            $product = shopProduct(['name' => 'Caricatore Brezza 65 W', 'stock' => 1]);
            cartRow($user, $product, 2);

            return $product;
        },
        ['product' => 'Caricatore Brezza 65 W', 'requested' => 2, 'available' => 1],
        1,
    ],
]);

it('refuses an empty cart', function (bool $withCart) {
    [$user, $address] = checkoutCustomer();

    if ($withCart) {
        $user->cart()->create();
    }

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))
        ->assertConflict()
        ->assertExactJson(['message' => 'Il carrello è vuoto.', 'code' => 'cart_empty']);

    expect(Order::query()->count())->toBe(0);
})->with(['without a cart' => false, 'with an empty cart' => true]);

it('never stores the full card number or the CVC', function (string $number) {
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id, ['number' => $number, 'cvc' => '987']));

    $digits = str_replace(' ', '', $number);
    $this->assertDatabaseMissing('payments', ['card_last_four' => $digits]);

    foreach (['orders', 'order_items', 'order_status_changes', 'payments'] as $table) {
        $dump = json_encode(DB::table($table)->get());

        expect($dump)->not->toContain($digits)
            ->and($dump)->not->toContain('987')
            ->and($dump)->not->toContain('08/29');
    }
})->with(['approved' => '4242 4242 4242 4242', 'declined' => '4000 0000 0000 0002']);

it('writes neither the card number nor the CVC in the logs', function (string $number) {
    Log::spy();
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id, ['number' => $number, 'cvc' => '987']));

    $digits = str_replace(' ', '', $number);
    $mentions = fn (...$arguments) => str_contains(json_encode($arguments), $digits) || str_contains(json_encode($arguments), '987');

    foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'] as $level) {
        Log::shouldNotHaveReceived($level, $mentions);
    }
})->with(['approved' => '4242 4242 4242 4242', 'declined' => '4000 0000 0000 0002']);

it('rejects a guest', function () {
    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload(1))->assertUnauthorized();
});

it('rejects a customer whose email is not verified', function () {
    $user = actingAsCustomer();
    $address = Address::factory()->for($user)->create();
    cartRow($user, shopProduct(), 1);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))
        ->assertForbidden()
        ->assertExactJson(['message' => 'Conferma il tuo indirizzo email per continuare.']);

    expect(Order::query()->count())->toBe(0);
});

it('rejects an administrator before anything else', function () {
    $admin = actingAsAdmin();
    $address = Address::factory()->for(User::factory()->customer())->create();

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))
        ->assertForbidden()
        ->assertExactJson(['message' => 'Gli account di amministrazione non possono fare acquisti.']);

    expect(Order::query()->count())->toBe(0);
});

it('answers 401 without effects when the account was deleted in the meantime', function () {
    [$user, $address] = checkoutCustomer();
    $product = shopProduct(['stock' => 4]);
    cartRow($user, $product, 1);
    anonymizeBehindTheSession($user);

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Devi accedere per continuare.']);

    expect(Order::query()->count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(4);
});

it('runs the checkouts of a session one at a time', function () {
    $route = Route::getRoutes()->getByName('v1.checkout');

    expect($route->locksFor())->toBe(10)
        ->and($route->waitsFor())->toBe(10)
        ->and($route->gatherMiddleware())->toContain('can:purchase,'.User::class, 'verified');
});
