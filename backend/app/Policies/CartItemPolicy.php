<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A customer changes only the rows of the own cart. A row of somebody else
 * answers 404, as a personal resource of another user always does.
 */
final class CartItemPolicy
{
    public function update(User $user, CartItem $cartItem): Response
    {
        return $this->owns($user, $cartItem);
    }

    public function delete(User $user, CartItem $cartItem): Response
    {
        return $this->owns($user, $cartItem);
    }

    private function owns(User $user, CartItem $cartItem): Response
    {
        $owned = Cart::query()->whereKey($cartItem->cart_id)->whereBelongsTo($user)->exists();

        return $owned ? Response::allow() : Response::denyAsNotFound();
    }
}
