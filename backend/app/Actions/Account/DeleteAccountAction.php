<?php

namespace App\Actions\Account;

use App\Actions\Shop\GetSupportSectionsAction;
use App\Exceptions\Account\AccountHasOpenOrdersException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Deletes the account of a customer by anonymizing it (D13): orders, items,
 * status history and payments stay for the retention obligation, everything
 * else of the user is erased. Only customers get here (UserPolicy::delete).
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
 * No security event: the log of the user has just been erased. Passkeys
 * join the deletion in Phase 3c, with the table of the library.
 */
final class DeleteAccountAction
{
    public function __construct(
        private readonly Request $request,
        private readonly GetSupportSectionsAction $getSupportSections,
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

            $locked->addresses()->delete();
            CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $locked->id))->delete();
            $locked->cart()->delete();
            $locked->securityEvents()->delete();
            $locked->emailChangeRequests()->delete();
            DB::table(config('auth.passwords.users.table'))->where('email', $originalEmail)->delete();
            DB::table(config('session.table'))->where('user_id', $locked->id)->delete();

            $locked->forceFill([
                'name' => User::ANONYMIZED_NAME,
                'email' => $locked->anonymizedEmail(),
                'password' => Str::random(64),
                'email_verified_at' => null,
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_step' => null,
                'anonymized_at' => now(),
            ])->save();

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
