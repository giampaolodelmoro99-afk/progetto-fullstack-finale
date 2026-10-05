<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The public catalog shows only active products: a deactivated product
 * answers 404, for guests, customers and admins alike, so its existence is
 * never revealed. Soft deleted products never reach the policy, because
 * the route binding leaves them out. The view ability stays free for the
 * admin area, where deactivated products are visible.
 */
final class ProductPolicy
{
    public function viewInShop(?User $user, Product $product): Response
    {
        return $product->is_active ? Response::allow() : Response::denyAsNotFound();
    }
}
