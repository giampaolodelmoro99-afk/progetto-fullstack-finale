<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\User;
use App\Support\Auth\ActiveUserLock;
use Illuminate\Support\Facades\DB;

/**
 * Removes a row from the cart. A row already removed by another request
 * leaves nothing to do.
 */
final class RemoveCartItemAction
{
    public function execute(User $user, CartItem $cartItem): void
    {
        DB::transaction(function () use ($user, $cartItem): void {
            $locked = ActiveUserLock::acquire($user);

            CartItem::query()
                ->whereKey($cartItem->id)
                ->whereIn('cart_id', $locked->cart()->select('id'))
                ->delete();
        });
    }
}
