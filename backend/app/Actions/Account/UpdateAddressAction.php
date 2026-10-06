<?php

namespace App\Actions\Account;

use App\Models\Address;
use App\Support\Auth\ActiveUserLock;
use Illuminate\Support\Facades\DB;

/**
 * Partial update of an address. is_default true makes it the only default
 * address of the user; is_default false on the default address is ignored,
 * because the default changes only by choosing another address ("Imposta
 * come predefinito"), so a customer with addresses always has one default.
 */
final class UpdateAddressAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data): Address {
            $user = ActiveUserLock::acquire($address->user_id);
            $locked = Address::query()->lockForUpdate()->findOrFail($address->id);

            $makeDefault = (bool) ($data['is_default'] ?? false);
            unset($data['is_default']);

            if ($makeDefault && ! $locked->is_default) {
                $user->addresses()->update(['is_default' => false]);
                $locked->is_default = true;
            }

            $locked->fill($data)->save();

            return $locked;
        });
    }
}
