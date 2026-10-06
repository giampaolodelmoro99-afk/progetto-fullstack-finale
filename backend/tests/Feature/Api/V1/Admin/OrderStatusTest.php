<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderShippedNotification;
use App\Support\PaymentResult;
use Illuminate\Support\Facades\Notification;
use Tests\Support\ScriptedPaymentGateway;

beforeEach(function () {
    $this->travelTo('2026-09-29 10:00:00');
    Notification::fake();
    $this->admin = User::factory()->admin()->create(['name' => 'Marta Lenzi']);
    $this->actingAs($this->admin, 'web');
});

it('ships a paid order with the admin as author and notifies the customer', function () {
    $order = Order::factory()->paid()->create(['created_at' => '2026-09-28 18:41:00']);

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'shipped'])
        ->assertOk()
        ->assertJsonPath('data.status', ['value' => 'shipped', 'label' => 'Spedito'])
        ->assertJsonPath('data.allowed_transitions', ['delivered'])
        ->assertJsonPath('data.can_cancel', false);

    expect($response->json('data.status_history.2'))->toBe([
        'status' => ['value' => 'shipped', 'label' => 'Spedito'],
        'description' => 'Segnato come spedito da Marta Lenzi',
        'changed_by' => ['id' => $this->admin->id, 'name' => 'Marta Lenzi'],
        'reason' => null,
        'created_at' => '2026-09-29T10:00:00+02:00',
    ]);
    $this->assertDatabaseHas('order_status_changes', [
        'order_id' => $order->id,
        'from_status' => 'paid',
        'to_status' => 'shipped',
        'changed_by' => $this->admin->id,
    ]);
    expect($order->fresh()->shipped_at->toIso8601String())->toBe('2026-09-29T10:00:00+02:00');
    Notification::assertSentToTimes($order->user, OrderShippedNotification::class, 1);
});

it('delivers a shipped order with the admin as author and notifies the customer', function () {
    $order = Order::factory()->shipped()->create(['created_at' => '2026-09-20 18:41:00']);

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])
        ->assertOk()
        ->assertJsonPath('data.status', ['value' => 'delivered', 'label' => 'Consegnato'])
        ->assertJsonPath('data.allowed_transitions', [])
        ->assertJsonPath('data.status_history.3.description', 'Segnato come consegnato da Marta Lenzi');

    expect($order->fresh()->delivered_at->toIso8601String())->toBe('2026-09-29T10:00:00+02:00');
    Notification::assertSentToTimes($order->user, OrderDeliveredNotification::class, 1);
});

it('cancels a paid order with the refund, the stock given back and the admin as author', function () {
    $gateway = useGateway(new ScriptedPaymentGateway);
    $headphones = shopProduct(['stock' => 5]);
    $order = Order::factory()
        ->paid()
        ->has(OrderItem::factory()->for($headphones)->state(['quantity' => 2]), 'items')
        ->create(['created_at' => '2026-09-28 18:41:00']);

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', ['value' => 'cancelled', 'label' => 'Annullato'])
        ->assertJsonPath('data.payment.status', ['value' => 'refunded', 'label' => 'Rimborsato'])
        ->assertJsonPath('data.status_history.2.description', 'Annullato e rimborsato da Marta Lenzi')
        ->assertJsonPath('data.status_history.2.changed_by', ['id' => $this->admin->id, 'name' => 'Marta Lenzi']);

    expect($gateway->refunds)->toBe(1)
        ->and($headphones->fresh()->stock)->toBe(7)
        ->and($order->payments()->value('status'))->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->cancelled_at->toIso8601String())->toBe('2026-09-29T10:00:00+02:00');
    Notification::assertSentToTimes($order->user, OrderCancelledNotification::class, 1);
});

it('changes nothing when the refund fails', function () {
    useGateway(new ScriptedPaymentGateway(onRefund: fn () => PaymentResult::failure('Rimborso non riuscito')));
    $headphones = shopProduct(['stock' => 5]);
    $order = Order::factory()
        ->paid()
        ->has(OrderItem::factory()->for($headphones)->state(['quantity' => 2]), 'items')
        ->create();

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
        ->assertStatus(502)
        ->assertJsonPath('code', 'refund_failed');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->cancelled_at)->toBeNull()
        ->and($headphones->fresh()->stock)->toBe(5)
        ->and($order->payments()->value('status'))->toBe(PaymentStatus::Succeeded)
        ->and($order->statusChanges()->count())->toBe(2);
    Notification::assertNothingSent();
});

it('rejects the statuses the admin cannot set by hand', function (mixed $status) {
    $order = Order::factory()->paid()->create();

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $status])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
})->with([
    'paid' => ['paid'],
    'pending' => ['pending'],
    'unknown' => ['lost'],
    'empty' => [''],
    'null' => [null],
]);

it('explains in italian which statuses can be set', function () {
    $order = Order::factory()->paid()->create();

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'paid'])
        ->assertJsonPath('errors.status.0', 'Puoi segnare un ordine solo come spedito, consegnato o annullato.');
});

it('rejects a transition that the current status does not allow, without effects', function (string $state, string $status) {
    $gateway = useGateway(new ScriptedPaymentGateway);
    $order = Order::factory()->{$state}()->create();
    $before = $order->fresh();
    $changes = $order->statusChanges()->count();

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $status])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Lo stato attuale dell\'ordine non consente questa operazione.',
            'code' => 'invalid_order_transition',
        ]);

    expect($order->fresh()->status)->toBe($before->status)
        ->and($order->statusChanges()->count())->toBe($changes)
        ->and($gateway->refunds)->toBe(0);
    Notification::assertNothingSent();
})->with([
    'delivered on paid' => ['paid', 'delivered'],
    'shipped on shipped' => ['shipped', 'shipped'],
    'shipped on pending' => ['pending', 'shipped'],
    'shipped on delivered' => ['delivered', 'shipped'],
    'delivered on a failed attempt' => ['failedPayment', 'delivered'],
    'cancelled on pending' => ['pending', 'cancelled'],
    'cancelled on shipped' => ['shipped', 'cancelled'],
    'cancelled on delivered' => ['delivered', 'cancelled'],
    'cancelled on cancelled after payment' => ['cancelledAfterPayment', 'cancelled'],
    'cancelled on a failed attempt' => ['failedPayment', 'cancelled'],
]);

it('ships the order of an anonymized customer without notifying the fictitious address', function () {
    $order = Order::factory()->paid()->for(User::factory()->anonymized())->create();

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'shipped'])
        ->assertOk()
        ->assertJsonPath('data.customer', ['name' => 'Cliente eliminato', 'email' => null]);

    Notification::assertNothingSent();
});

it('answers 404 for an order that does not exist', function () {
    $this->patchJson('/api/v1/admin/orders/999999/status', ['status' => 'shipped'])->assertNotFound();
});
