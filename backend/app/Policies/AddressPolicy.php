<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A customer manages only the own addresses. The address of somebody else
 * answers 404, as a personal resource of another user always does, so its
 * existence is never revealed.
 */
final class AddressPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Address $address): Response
    {
        return $this->owns($user, $address);
    }

    public function update(User $user, Address $address): Response
    {
        return $this->owns($user, $address);
    }

    public function delete(User $user, Address $address): Response
    {
        return $this->owns($user, $address);
    }

    private function owns(User $user, Address $address): Response
    {
        return (int) $address->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
