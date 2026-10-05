<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The public catalog shows only active products: a deactivated product
 * answers 404, for guests, customers and admins alike, so its existence is
 * never revealed. Soft deleted products never reach the policy, because
 * the route binding leaves them out. The admin abilities belong to the
 * administrators only, who see deactivated products too; the images of a
 * product are managed with the update ability.
 */
final class ProductPolicy
{
    public function viewInShop(?User $user, Product $product): Response
    {
        return $product->is_active ? Response::allow() : Response::denyAsNotFound();
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }
}
