<?php

namespace App\Actions\Security;

use App\Models\Passkey;

/**
 * Renames a passkey of the user. Not a sensitive operation: no identity
 * confirmation, no event and no notice. The events written before keep
 * the name the passkey had then.
 */
final class RenamePasskeyAction
{
    public function execute(Passkey $passkey, string $name): Passkey
    {
        $passkey->forceFill(['name' => $name])->save();

        return $passkey;
    }
}
