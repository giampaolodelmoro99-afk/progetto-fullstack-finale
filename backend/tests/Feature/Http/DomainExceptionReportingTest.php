<?php

use App\Models\Order;
use App\Models\User;
use App\Support\PaymentResult;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\ScriptedPaymentGateway;

/**
 * Collects the lines of level error written from now on.
 *
 * @return ArrayObject<int, MessageLogged>
 */
function collectErrorLogs(): ArrayObject
{
    $logged = new ArrayObject;
    Log::listen(function (MessageLogged $event) use ($logged): void {
        if ($event->level === 'error') {
            $logged->append($event);
        }
    });

    return $logged;
}

it('does not log wrong credentials as an error', function () {
    User::factory()->create(['email' => 'giulia.ferri@esempio.it']);
    $logged = collectErrorLogs();

    fromFrontend()->postJson('/api/v1/login', ['email' => 'giulia.ferri@esempio.it', 'password' => 'sbagliata'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_credentials');

    expect($logged)->toHaveCount(0);
});

it('does not log a refused account deletion as an error', function () {
    $user = actingAsVerifiedCustomer();
    Order::factory()->paid()->for($user)->create();
    $logged = collectErrorLogs();

    confirmIdentity();
    fromFrontend()->deleteJson('/api/v1/me')
        ->assertConflict()
        ->assertJsonPath('code', 'account_has_open_orders');

    expect($logged)->toHaveCount(0);
});

it('does not log a declined card as an error', function () {
    [$user, $address] = checkoutCustomer();
    cartRow($user, shopProduct(), 1);
    $logged = collectErrorLogs();

    fromFrontend()->postJson('/api/v1/checkout', checkoutPayload($address->id, ['number' => '4000 0000 0000 0002']))
        ->assertStatus(402)
        ->assertJsonPath('code', 'payment_failed');

    expect($logged)->toHaveCount(0);
});

it('still logs a failed refund as an error', function () {
    $user = actingAsVerifiedCustomer();
    $order = Order::factory()->paid()->for($user)->create();
    useGateway(new ScriptedPaymentGateway(onRefund: fn () => PaymentResult::failure('Rimborso rifiutato')));
    $logged = collectErrorLogs();

    fromFrontend()->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertStatus(502)
        ->assertJsonPath('code', 'refund_failed');

    expect($logged)->toHaveCount(1)
        ->and($logged[0]->message)->toBe('Il rimborso non è riuscito: l\'ordine non è stato annullato. Riprova tra poco.');
});
