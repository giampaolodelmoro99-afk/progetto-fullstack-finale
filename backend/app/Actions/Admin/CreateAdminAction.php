<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Creates an admin from the admin:create command. The email counts as
 * verified, because whoever runs the command has access to the server;
 * no email is sent and no session starts. An existing customer is never
 * promoted: a taken email is the same 422 message as the registration.
 */
final class CreateAdminAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function execute(#[\SensitiveParameter] array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'password_changed_at' => now(),
        ]);
        $user->role = UserRole::Admin;
        $user->email_verified_at = now();

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Questo indirizzo email è già in uso.']);
        }

        return $user;
    }
}
