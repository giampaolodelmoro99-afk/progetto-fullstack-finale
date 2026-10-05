<?php

namespace App\Policies;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A user manages only the own passkeys. The passkeys of somebody else answer
 * 404, so their existence is never revealed.
 */
final class PasskeyPolicy
{
    public function update(User $user, Passkey $passkey): Response
    {
        return $this->owns($user, $passkey);
    }

    public function delete(User $user, Passkey $passkey): Response
    {
        return $this->owns($user, $passkey);
    }

    private function owns(User $user, Passkey $passkey): Response
    {
        return (int) $passkey->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
