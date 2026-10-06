<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Actions\Cart\AddCartItemAction;
use App\Actions\Cart\GetCartAction;
use App\Actions\Cart\RemoveCartItemAction;
use App\Actions\Cart\UpdateCartItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Rows of the cart. Every write answers with the whole updated cart, the
 * removal too (200 instead of 204), as CartController does.
 */
final class CartItemController extends Controller
{
    public function store(StoreCartItemRequest $request, AddCartItemAction $addCartItem, GetCartAction $getCart): JsonResponse
    {
        $addCartItem->execute($request->user(), (int) $request->validated('product_id'), (int) $request->validated('quantity'));

        return CartResource::make($getCart->execute($request->user()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem, UpdateCartItemAction $updateCartItem, GetCartAction $getCart): CartResource
    {
        $updateCartItem->execute($request->user(), $cartItem, (int) $request->validated('quantity'));

        return CartResource::make($getCart->execute($request->user()));
    }

    public function destroy(Request $request, CartItem $cartItem, RemoveCartItemAction $removeCartItem, GetCartAction $getCart): CartResource
    {
        Gate::authorize('delete', $cartItem);

        $removeCartItem->execute($request->user(), $cartItem);

        return CartResource::make($getCart->execute($request->user()));
    }
}
