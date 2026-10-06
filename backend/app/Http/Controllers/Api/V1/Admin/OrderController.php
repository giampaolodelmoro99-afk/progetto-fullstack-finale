<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\SearchOrdersAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Order\IndexOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Http\Resources\Admin\OrderSummaryResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Every order, rejected payment attempts and pending orders included.
 */
final class OrderController extends Controller
{
    public function index(IndexOrderRequest $request, SearchOrdersAction $searchOrders): AnonymousResourceCollection
    {
        $orders = $searchOrders->execute($request->filters())
            ->with('user')
            ->withSum('items', 'quantity')
            ->paginate($request->perPage())
            ->withQueryString();

        return OrderSummaryResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return OrderResource::make($order->loadAdminDetail());
    }
}
