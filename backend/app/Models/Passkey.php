<?php

namespace App\Models;

use Database\Factories\PasskeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Passkeys\Passkey as BasePasskey;

/**
 * WebAuthn credential of a user (laravel/passkeys). credential holds only
 * public data (public key, signature counter, user handle, AAGUID) and never
 * leaves the backend. The authenticator name guessed from the AAGUID is not
 * appended: the responses carry only the name chosen by the user.
 */
class Passkey extends BasePasskey
{
    /** @use HasFactory<PasskeyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = ['credential'];

    /**
     * @var list<string>
     */
    protected $appends = [];
}
