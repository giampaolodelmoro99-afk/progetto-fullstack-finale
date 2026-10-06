<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\UpdateOrderStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Order\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Order;

/**
 * Status change by the admin: shipped, delivered or cancelled with the
 * refund. 422 when the current status does not allow it, 502 when the
 * refund fails.
 */
final class OrderStatusController extends Controller
{
    public function __invoke(UpdateOrderStatusRequest $request, Order $order, UpdateOrderStatusAction $updateStatus): OrderResource
    {
        $updated = $updateStatus->execute($order, $request->targetStatus(), $request->user());

        return OrderResource::make($updated->loadAdminDetail());
    }
}
