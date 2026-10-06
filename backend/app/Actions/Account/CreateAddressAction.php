<?php

namespace App\Actions\Account;

use App\Models\Address;
use App\Models\User;
use App\Support\Auth\ActiveUserLock;
use Illuminate\Support\Facades\DB;

/**
 * Saves a shipping address of the customer; the country is always IT (model
 * default). The first address becomes the default one; a default address
 * takes the flag away from the others in the same transaction, under the
 * lock of the user, because MySQL has no partial unique index (N4).
 */
final class CreateAddressAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            $locked = ActiveUserLock::acquire($user);

            $isDefault = (bool) ($data['is_default'] ?? false) || ! $locked->addresses()->exists();

            if ($isDefault) {
                $locked->addresses()->update(['is_default' => false]);
            }

            return $locked->addresses()->create([...$data, 'is_default' => $isDefault]);
        });
    }
}
