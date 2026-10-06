<?php

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Enums\OrderStatus;
use App\Exceptions\Orders\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\User;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderShippedNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(Carbon\Carbon::parse('2026-09-30 11:15:00', 'Europe/Rome'));
});

it('ships a paid order with the admin as author and notifies the customer', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create(['name' => 'Marta Lenzi']);
    $order = Order::factory()->paid()->create(['created_at' => '2026-09-28 18:41:00']);

    $shipped = app(ChangeOrderStatusAction::class)->execute($order, OrderStatus::Shipped, $admin);

    expect($shipped->status)->toBe(OrderStatus::Shipped)
        ->and($shipped->shipped_at->toDateTimeString())->toBe('2026-09-30 11:15:00');

    $change = OrderStatusChange::query()->whereBelongsTo($order)->latest('id')->first();
    expect([$change->from_status, $change->to_status, $change->changed_by, $change->reason, $change->created_at->toDateTimeString()])
        ->toBe([OrderStatus::Paid, OrderStatus::Shipped, $admin->id, null, '2026-09-30 11:15:00'])
        ->and($change->description(withAuthor: true))->toBe('Segnato come spedito da Marta Lenzi')
        ->and($change->description(withAuthor: false))->toBe('Segnato come spedito');

    Notification::assertSentTo($order->user, OrderShippedNotification::class);
    Notification::assertSentTimes(OrderShippedNotification::class, 1);
});

it('delivers a shipped order and notifies the customer', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->shipped($admin)->create(['created_at' => '2026-09-27 10:00:00']);

    $delivered = app(ChangeOrderStatusAction::class)->execute($order, OrderStatus::Delivered, $admin);

    expect($delivered->status)->toBe(OrderStatus::Delivered)
        ->and($delivered->delivered_at->toDateTimeString())->toBe('2026-09-30 11:15:00');
    Notification::assertSentTo($order->user, OrderDeliveredNotification::class);
    Notification::assertSentTimes(OrderDeliveredNotification::class, 1);
});

it('sends no email for the transitions of the system', function (OrderStatus $to) {
    Notification::fake();
    $order = Order::factory()->pending()->create();

    app(ChangeOrderStatusAction::class)->execute($order, $to);

    Notification::assertNothingSent();
})->with([OrderStatus::Paid, OrderStatus::Cancelled]);

it('refuses a transition that the status does not allow', function (string $state, OrderStatus $to) {
    Notification::fake();
    $order = Order::factory()->{$state}()->create();
    $rows = OrderStatusChange::query()->count();

    expect(fn () => app(ChangeOrderStatusAction::class)->execute($order, $to, User::factory()->admin()->create()))
        ->toThrow(InvalidOrderTransitionException::class);

    expect($order->fresh()->status)->toBe($order->status)
        ->and(OrderStatusChange::query()->count())->toBe($rows);
    Notification::assertNothingSent();
})->with([
    'pending to shipped' => ['pending', OrderStatus::Shipped],
    'paid to delivered' => ['paid', OrderStatus::Delivered],
    'paid to pending' => ['paid', OrderStatus::Pending],
    'shipped to cancelled' => ['shipped', OrderStatus::Cancelled],
    'delivered to cancelled' => ['delivered', OrderStatus::Cancelled],
    'cancelled to paid' => ['cancelledAfterPayment', OrderStatus::Paid],
]);

it('sends nothing to the fictitious address of an anonymized account', function () {
    Notification::fake();
    $order = Order::factory()->paid()->create();
    $order->user->forceFill(['anonymized_at' => now(), 'email' => "deleted-{$order->user_id}@anonymized.invalid"])->save();

    app(ChangeOrderStatusAction::class)->execute($order, OrderStatus::Shipped, User::factory()->admin()->create());

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
    Notification::assertNothingSent();
});

it('sends the email only after the commit of the outer transaction', function (bool $fails) {
    Event::fake([NotificationSent::class]);
    $order = Order::factory()->paid()->create();
    $admin = User::factory()->admin()->create();

    try {
        DB::transaction(function () use ($order, $admin, $fails) {
            app(ChangeOrderStatusAction::class)->execute($order, OrderStatus::Shipped, $admin);

            if ($fails) {
                throw new RuntimeException('Outer transaction failed');
            }
        });
    } catch (RuntimeException) {
    }

    expect($order->fresh()->status)->toBe($fails ? OrderStatus::Paid : OrderStatus::Shipped);
    Event::assertDispatchedTimes(NotificationSent::class, $fails ? 0 : 1);
})->with(['committed' => false, 'rolled back' => true]);
