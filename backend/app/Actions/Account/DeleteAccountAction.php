<?php

namespace App\Actions\Account;

use App\Actions\Shop\GetSupportSectionsAction;
use App\Exceptions\Account\AccountHasOpenOrdersException;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Deletes the account of a customer by anonymizing it (D13) with
 * AnonymizeUserAction, shared with the removal of an admin. Only customers
 * get here (UserPolicy::delete).
 *
 * Open orders are checked first, then again under the row lock: a checkout
 * arriving later finds the sessions closed. Original email and name are read
 * under the lock, so an email change confirmed in the meantime is seen; the
 * notice goes to the original address, on demand and after the commit, so it
 * never reaches the .invalid address and never leaves for a deletion that
 * failed. A second request that waited for the lock finds the account
 * already anonymized and does nothing.
 *
 * Before the logout the user is read again: the guard would otherwise cycle
 * the stale remember token kept in memory and save it on the anonymized user.
 *
 * No security event: the log of the user has just been erased.
 */
final class DeleteAccountAction
{
    public function __construct(
        private readonly Request $request,
        private readonly GetSupportSectionsAction $getSupportSections,
        private readonly AnonymizeUserAction $anonymizeUser,
    ) {}

    public function execute(User $user): void
    {
        $this->ensureNoOpenOrders($user);

        $original = DB::transaction(function () use ($user): ?array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->isAnonymized()) {
                return null;
            }

            $this->ensureNoOpenOrders($locked);

            $originalEmail = $locked->email;
            $originalName = $locked->name;

            $this->anonymizeUser->execute($locked);

            return ['email' => $originalEmail, 'name' => $originalName];
        });

        if ($original !== null) {
            Notification::route('mail', $original['email'])->notify(
                (new AccountDeletedNotification($original['name'], now(), $this->contacts()))->afterCommit(),
            );
        }

        $user->refresh();
        Auth::guard('web')->logout();

        if ($this->request->hasSession()) {
            $this->request->session()->invalidate();
            $this->request->session()->regenerateToken();
        }
    }

    private function ensureNoOpenOrders(User $user): void
    {
        if (Order::query()->whereBelongsTo($user)->openForAccountDeletion()->exists()) {
            throw new AccountHasOpenOrdersException;
        }
    }

    /**
     * @return list<string>
     */
    private function contacts(): array
    {
        $contacts = collect($this->getSupportSections->execute())->firstWhere('id', 'contatti');

        return $contacts['paragraphs'] ?? [];
    }
}
