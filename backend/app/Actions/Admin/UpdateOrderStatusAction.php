<?php

namespace App\Actions\Admin;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Enums\OrderStatus;
use App\Exceptions\Orders\InvalidOrderTransitionException;
use App\Exceptions\Orders\RefundFailedException;
use App\Models\Order;
use App\Models\User;

/**
 * Status change by the admin. The cancellation goes through
 * CancelOrderAction, with the refund and the stock given back; shipped and
 * delivered through ChangeOrderStatusAction. In both cases the admin is the
 * author of the history row.
 */
final class UpdateOrderStatusAction
{
    public function __construct(
        private readonly CancelOrderAction $cancelOrder,
        private readonly ChangeOrderStatusAction $changeStatus,
    ) {}

    /**
     * @throws InvalidOrderTransitionException
     * @throws RefundFailedException
     */
    public function execute(Order $order, OrderStatus $to, User $admin): Order
    {
        return $to === OrderStatus::Cancelled
            ? $this->cancelOrder->execute($order, $admin)
            : $this->changeStatus->execute($order, $to, $admin);
    }
}
