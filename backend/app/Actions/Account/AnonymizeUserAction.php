<?php

namespace App\Actions\Account;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The anonymization shared by the account deletion of a customer and the
 * removal of an admin (D13): orders, items, status history and payments
 * stay, everything else of the user is erased and the account can no
 * longer log in. The user is never deleted, because orders and status
 * changes still point to it.
 *
 * Must run inside a database transaction, on the user row already locked
 * by the caller, which reads the original email and name first.
 */
final class AnonymizeUserAction
{
    public function execute(User $locked): void
    {
        $locked->addresses()->delete();
        CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $locked->id))->delete();
        $locked->cart()->delete();
        $locked->securityEvents()->delete();
        $locked->emailChangeRequests()->delete();
        $locked->passkeys()->delete();
        DB::table(config('auth.passwords.users.table'))->where('email', $locked->email)->delete();
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
    }
}
