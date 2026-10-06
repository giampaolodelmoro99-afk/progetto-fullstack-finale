<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;

/**
 * First step of the transactions that change the personal data of a user
 * (addresses, cart): the row of the user is locked before any related row,
 * the lock order of every action, so they never deadlock with the account
 * deletion. Under the lock an anonymized account is refused as a closed
 * session (401): a request that started before the deletion finds the
 * account gone and changes nothing.
 */
final class ActiveUserLock
{
    /**
     * Must run inside a database transaction.
     *
     * @throws AuthenticationException
     */
    public static function acquire(User|int $user): User
    {
        $locked = User::query()->lockForUpdate()->findOrFail($user instanceof User ? $user->id : $user);

        if ($locked->isAnonymized()) {
            throw new AuthenticationException;
        }

        return $locked;
    }
}
