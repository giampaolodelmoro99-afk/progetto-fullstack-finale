<?php

namespace App\Console\Commands;

use App\Actions\Checkout\SettleOrderPaymentAction;
use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Throwable;

/**
 * Completes the checkouts interrupted between the charge and the second
 * transaction: pending orders older than account_deletion_pending_window
 * minutes (the same window after which a pending order no longer counts as
 * a payment in progress). The charge is asked again with the same
 * idempotency key, so the gateway returns the first outcome without a
 * second charge, and SettleOrderPaymentAction records it exactly as the
 * checkout does. Without card data, a charge the gateway never received
 * comes back as failed: the order is cancelled and the stock given back.
 * A gateway error leaves the order pending for the next run.
 */
final class ReconcilePendingOrdersCommand extends Command
{
    protected $signature = 'orders:reconcile-pending';

    protected $description = 'Complete the pending orders left by an interrupted checkout';

    public function handle(PaymentGateway $gateway, SettleOrderPaymentAction $settlePayment): int
    {
        $orders = Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(config('security.account_deletion_pending_window')))
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->components->info('No pending orders to reconcile.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($orders as $order) {
            try {
                $settled = $settlePayment->execute($order, $gateway->charge($order, []));
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("{$order->number}: payment gateway error, left pending.");
                $failures++;

                continue;
            }

            $this->components->info("{$order->number}: {$settled->status->value}.");
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
