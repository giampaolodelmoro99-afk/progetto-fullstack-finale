<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Actions\Cart\ClearCartAction;
use App\Actions\Cart\GetCartAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use Illuminate\Http\Request;

/**
 * Every write of the cart answers with the updated cart, the emptying too
 * (200 instead of 204): the frontend applies one rule and needs no second
 * request.
 */
final class CartController extends Controller
{
    public function show(Request $request, GetCartAction $getCart): CartResource
    {
        return CartResource::make($getCart->execute($request->user()));
    }

    public function destroy(Request $request, ClearCartAction $clearCart, GetCartAction $getCart): CartResource
    {
        $clearCart->execute($request->user());

        return CartResource::make($getCart->execute($request->user()));
    }
}
