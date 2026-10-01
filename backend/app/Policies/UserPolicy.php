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
    public function delete(User $user): Response
    {
        return $user->isAdmin()
            ? Response::deny('Gli account di amministrazione non si possono eliminare.')
            : Response::allow();
    }
}
