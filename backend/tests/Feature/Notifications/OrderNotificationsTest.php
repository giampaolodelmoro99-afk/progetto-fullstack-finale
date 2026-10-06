<?php

use App\Events\OrderPlaced;
use App\Listeners\SendOrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderShippedNotification;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * The paid order of the purchase mockup, ORD-2026-… with 496,00 €.
 */
function mockupOrder(string $state = 'paid'): Order
{
    $user = User::factory()->customer()->create(['name' => 'Giulia Ferri']);

    return Order::factory()
        ->{$state}()
        ->for($user)
        ->has(OrderItem::factory()->state(['product_name' => 'Cuffie Aria', 'unit_price' => 34900, 'quantity' => 1]), 'items')
        ->has(OrderItem::factory()->state(['product_name' => 'Caricatore Brezza 65 W', 'unit_price' => 5900, 'quantity' => 2]), 'items')
        ->has(OrderItem::factory()->state(['product_name' => 'Cavo Seta USB-C 1 m', 'unit_price' => 2900, 'quantity' => 1]), 'items')
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
}

it('writes the order confirmation in italian with rows, totals and address', function () {
    $order = mockupOrder();

    $mail = (new OrderPlacedNotification($order))->toMail($order->user);

    expect($mail->subject)->toBe("Conferma dell'ordine {$order->number}")
        ->and($mail->greeting)->toBe('Ciao Giulia Ferri,')
        ->and($mail->introLines)->toBe([
            "grazie per il tuo ordine {$order->number}: il pagamento è andato a buon fine.",
            'Cuffie Aria × 1: 349,00 €',
            'Caricatore Brezza 65 W × 2: 118,00 €',
            'Cavo Seta USB-C 1 m × 1: 29,00 €',
            'Subtotale: 496,00 €',
            'Spedizione: Gratuita',
            'Totale: 496,00 € (IVA inclusa)',
            'Spedizione a: Giulia Ferri, Via San Martino 42, 56125 Pisa (PI), Italia',
        ])
        ->and($mail->actionText)->toBe('Vedi l\'ordine')
        ->and($mail->actionUrl)->toBe("http://localhost:5173/account/orders/{$order->id}")
        ->and($mail->outroLines)->toBe(['Ti scriveremo di nuovo quando l\'ordine sarà spedito.']);
});

it('shows the shipping cost in the confirmation below the threshold', function () {
    $order = Order::factory()->paid()
        ->has(OrderItem::factory()->state(['product_name' => 'Cavo Seta USB-C 1 m', 'unit_price' => 2900, 'quantity' => 2]), 'items')
        ->create();

    $mail = (new OrderPlacedNotification($order))->toMail($order->user);

    expect($mail->introLines)->toContain('Subtotale: 58,00 €', 'Spedizione: 6,90 €', 'Totale: 64,90 € (IVA inclusa)');
});

it('writes the shipping notice in italian', function () {
    $order = mockupOrder('shipped');

    $mail = (new OrderShippedNotification($order))->toMail($order->user);

    expect($mail->subject)->toBe("Il tuo ordine {$order->number} è stato spedito")
        ->and($mail->greeting)->toBe('Ciao Giulia Ferri,')
        ->and($mail->introLines)->toBe([
            "il tuo ordine {$order->number} è partito: la consegna è prevista in 24/48 ore.",
            'Spedizione a: Giulia Ferri, Via San Martino 42, 56125 Pisa (PI), Italia',
        ])
        ->and($mail->actionUrl)->toBe("http://localhost:5173/account/orders/{$order->id}");
});

it('writes the delivery notice in italian', function () {
    $order = mockupOrder('delivered');

    $mail = (new OrderDeliveredNotification($order))->toMail($order->user);

    expect($mail->subject)->toBe("Il tuo ordine {$order->number} è stato consegnato")
        ->and($mail->introLines)->toBe([
            "il tuo ordine {$order->number} è stato consegnato. Grazie per aver scelto Casa del Silicio.",
            'Se qualcosa non va, hai 30 giorni per il reso: trovi i dettagli nella pagina Assistenza.',
        ]);
});

it('writes the cancellation notice with the refunded amount and card', function () {
    $order = mockupOrder('cancelledAfterPayment');

    $mail = (new OrderCancelledNotification($order))->toMail($order->user);

    expect($mail->subject)->toBe("Ordine {$order->number} annullato")
        ->and($mail->introLines)->toBe([
            "il tuo ordine {$order->number} è stato annullato e non verrà spedito.",
            'Ti abbiamo rimborsato 496,00 € sulla carta che termina con 4242: i tempi di accredito dipendono dalla tua banca.',
        ]);
});

it('queues every order email', function (string $class) {
    expect(new $class(Order::factory()->make()))->toBeInstanceOf(ShouldQueue::class);
})->with([
    OrderPlacedNotification::class,
    OrderShippedNotification::class,
    OrderDeliveredNotification::class,
    OrderCancelledNotification::class,
]);

it('sends the confirmation of a placed order, but not to an anonymized account', function (bool $anonymized) {
    Notification::fake();
    $order = Order::factory()->paid()->create();

    if ($anonymized) {
        anonymizeBehindTheSession($order->user);
    }

    (new SendOrderConfirmation)->handle(new OrderPlaced($order->fresh()));

    Notification::assertSentTimes(OrderPlacedNotification::class, $anonymized ? 0 : 1);
})->with(['active account' => false, 'anonymized account' => true]);

it('dispatches the placed order only after the commit', function () {
    expect(new OrderPlaced(Order::factory()->make()))->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('queues the confirmation listener', function () {
    expect(new SendOrderConfirmation)->toBeInstanceOf(ShouldQueue::class);
});
