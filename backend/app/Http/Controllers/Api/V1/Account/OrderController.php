<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Orders of the customer: only the ones paid at least once, in the list
 * (visibleToCustomer) and in the detail (OrderPolicy, 404 for the others).
 */
final class OrderController extends Controller
{
    public const PER_PAGE = 10;

    /**
     * Newest first, with the item count of each order.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->visibleToCustomer()
            ->withSum('items', 'quantity')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return OrderResource::make($order->loadCustomerDetail());
    }
}
