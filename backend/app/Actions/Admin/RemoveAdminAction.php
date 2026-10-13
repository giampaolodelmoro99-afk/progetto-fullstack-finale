<?php

namespace App\Actions\Admin;

use App\Actions\Account\AnonymizeUserAction;
use App\Enums\UserRole;
use App\Exceptions\Admin\AdminNotFoundException;
use App\Exceptions\Admin\LastAdminException;
use App\Models\User;
use App\Notifications\AdminRemovedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Removes an admin from the admin:remove command by anonymizing it like
 * the account deletion (AnonymizeUserAction): the user cannot be deleted,
 * because order_status_changes.changed_by restricts the deletion, and the
 * history shows "Amministratore eliminato". There are no open orders to
 * check: an admin does not buy.
 *
 * Every active admin row is locked, by ascending id, before counting: two
 * removals started together wait for each other and the second one sees
 * the first, so the shop never stays without admins. They are all rows of
 * users, so the lock order of the actions (user first) holds. Without an
 * index on users.role InnoDB locks every row it reads, customers included:
 * accepted, because the command is rare and the transaction lasts a few
 * milliseconds.
 *
 * The notice goes to the original address, read under the lock, on demand
 * and after the commit, as for the account deletion. No security event:
 * the log of the user has just been erased.
 */
final class RemoveAdminAction
{
    public function __construct(
        private readonly AnonymizeUserAction $anonymizeUser,
    ) {}

    /**
     * @throws AdminNotFoundException
     * @throws LastAdminException
     */
    public function execute(User $admin): void
    {
        $original = DB::transaction(function () use ($admin): array {
            $admins = User::query()
                ->where('role', UserRole::Admin)
                ->whereNull('anonymized_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $locked = $admins->firstWhere('id', $admin->id);

            if ($locked === null) {
                throw new AdminNotFoundException;
            }

            if ($admins->count() === 1) {
                throw new LastAdminException;
            }

            $original = ['email' => $locked->email, 'name' => $locked->name];

            $this->anonymizeUser->execute($locked);

            return $original;
        });

        Notification::route('mail', $original['email'])->notify(
            (new AdminRemovedNotification($original['name'], now()))->afterCommit(),
        );

        $admin->refresh();
    }
}
