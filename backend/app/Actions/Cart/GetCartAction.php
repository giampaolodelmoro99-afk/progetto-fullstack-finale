<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The cart of the user with what CartResource reads. A user who never added
 * a product has no cart row: an empty, unsaved cart is returned, without
 * creating it.
 */
final class GetCartAction
{
    public function execute(User $user): Cart
    {
        $cart = $user->cart()->with(['items.product.category', 'items.product.cover'])->first();

        return $cart ?? (new Cart)->setRelation('items', new Collection);
    }
}
