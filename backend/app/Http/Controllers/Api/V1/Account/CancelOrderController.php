<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Orders\CancelOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Cancellation by the customer, with the refund. 404 for orders that are
 * not visible to the customer; 422 when the status no longer allows it;
 * 502 when the refund fails.
 */
final class CancelOrderController extends Controller
{
    public function __invoke(Request $request, Order $order, CancelOrderAction $cancelOrder): OrderResource
    {
        Gate::authorize('cancel', $order);

        $cancelled = $cancelOrder->execute($order, $request->user());

        return OrderResource::make($cancelled->loadCustomerDetail());
    }
}
