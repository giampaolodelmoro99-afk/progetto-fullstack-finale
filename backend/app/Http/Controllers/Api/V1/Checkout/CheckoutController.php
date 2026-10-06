<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Actions\Checkout\PlaceOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creates the order from the cart and charges it. 201 with the paid order;
 * 409, 402 and 401 come from the exceptions of PlaceOrderAction.
 */
final class CheckoutController extends Controller
{
    public function __invoke(PlaceOrderRequest $request, PlaceOrderAction $placeOrder): JsonResponse
    {
        $validated = $request->validated();

        $order = $placeOrder->execute($request->user(), (int) $validated['address_id'], $validated['payment']);

        return OrderResource::make($order->loadCustomerDetail())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
