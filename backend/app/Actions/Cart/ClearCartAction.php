<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\User;
use App\Support\Auth\ActiveUserLock;
use Illuminate\Support\Facades\DB;

/**
 * Empties the cart: the rows go, the cart row stays.
 */
final class ClearCartAction
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = ActiveUserLock::acquire($user);

            CartItem::query()->whereIn('cart_id', $locked->cart()->select('id'))->delete();
        });
    }
}
