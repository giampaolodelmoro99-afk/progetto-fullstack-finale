<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Categories are managed by the administrators only. The public menu reads
 * them without a policy, because they have no visibility rules.
 */
final class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }
}
