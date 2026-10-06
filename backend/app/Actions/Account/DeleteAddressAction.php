<?php

namespace App\Actions\Account;

use App\Models\Address;
use App\Support\Auth\ActiveUserLock;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an address. When it was the default one, the most recent of the
 * remaining addresses becomes the default. Orders keep their own snapshot
 * of the address, so they do not change.
 */
final class DeleteAddressAction
{
    public function execute(Address $address): void
    {
        DB::transaction(function () use ($address): void {
            $user = ActiveUserLock::acquire($address->user_id);
            $locked = Address::query()->lockForUpdate()->findOrFail($address->id);

            $locked->delete();

            if ($locked->is_default) {
                $user->addresses()->orderByDesc('id')->first()?->update(['is_default' => true]);
            }
        });
    }
}
