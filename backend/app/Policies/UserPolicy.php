<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

final class UserPolicy
{
    /**
     * Only a customer deletes the own account: an administrator cannot, so
     * the shop never remains without administrators. Checked before the
     * identity confirmation, so an administrator is never asked for the
     * password of an operation that would be refused.
     */
    public const ADMIN_CANNOT_PURCHASE_MESSAGE = 'Gli account di amministrazione non possono fare acquisti.';

    /**
     * Addresses, cart, checkout and orders belong to customers only:
     * an administrator gets 403, a permission of the role, before any check
     * on the resource. Same reasons as the account deletion: management and
     * purchases stay separate, and orders of the administrator would distort
     * revenue and dashboard.
     */
    public function purchase(User $user): Response
    {
        return $user->isAdmin()
            ? Response::deny(self::ADMIN_CANNOT_PURCHASE_MESSAGE)
            : Response::allow();
    }

    public function delete(User $user): Response
    {
        return $user->isAdmin()
            ? Response::deny('Gli account di amministrazione non si possono eliminare.')
            : Response::allow();
    }
}
