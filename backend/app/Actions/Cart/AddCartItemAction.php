<?php

namespace App\Actions\Cart;

use App\Models\Product;
use App\Models\User;
use App\Support\Auth\ActiveUserLock;
use App\Support\CartMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a product to the cart, creating the cart at the first insert. A
 * product already in the cart gets the sum of the quantities; above the
 * maximum of the product the request is refused with the maximum in the
 * message, never reduced in silence. Lock order: the user, then the row of
 * the cart. The product is read without a lock: the cart reserves no stock.
 */
final class AddCartItemAction
{
    public function execute(User $user, int $productId, int $quantity): void
    {
        DB::transaction(function () use ($user, $productId, $quantity): void {
            $locked = ActiveUserLock::acquire($user);
            $product = Product::query()->find($productId);

            if ($product === null || ! $product->is_active || $product->stock <= 0) {
                throw ValidationException::withMessages(['product_id' => CartMessages::UNAVAILABLE_PRODUCT]);
            }

            $cart = $locked->cart()->firstOrCreate();
            $item = $cart->items()->where('product_id', $product->id)->lockForUpdate()->first();
            $total = ($item->quantity ?? 0) + $quantity;

            if ($total > $product->max_quantity) {
                throw ValidationException::withMessages(['quantity' => CartMessages::maxQuantity($product->max_quantity)]);
            }

            if ($item === null) {
                $cart->items()->create(['product_id' => $product->id, 'quantity' => $total]);
            } else {
                $item->update(['quantity' => $total]);
            }
        });
    }
}
