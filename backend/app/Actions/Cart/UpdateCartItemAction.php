<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\User;
use App\Support\Auth\ActiveUserLock;
use App\Support\CartMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets the quantity of a row, between 1 and the maximum of the product on
 * the current stock. A row that cannot be bought any more only leaves the
 * cart. Lock order: the user, then the row, read again inside the cart of
 * the user (a row removed in the meantime answers 404).
 */
final class UpdateCartItemAction
{
    public function execute(User $user, CartItem $cartItem, int $quantity): void
    {
        DB::transaction(function () use ($user, $cartItem, $quantity): void {
            $lockedUser = ActiveUserLock::acquire($user);
            $locked = CartItem::query()
                ->with('product')
                ->whereIn('cart_id', $lockedUser->cart()->select('id'))
                ->lockForUpdate()
                ->findOrFail($cartItem->id);

            if (! $locked->is_purchasable) {
                throw ValidationException::withMessages(['quantity' => CartMessages::ROW_NOT_PURCHASABLE]);
            }

            if ($quantity > $locked->product->max_quantity) {
                throw ValidationException::withMessages(['quantity' => CartMessages::maxQuantity($locked->product->max_quantity)]);
            }

            $locked->update(['quantity' => $quantity]);
        });
    }
}
