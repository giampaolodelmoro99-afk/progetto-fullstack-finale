<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Creates a customer, sends the verification email and starts the session.
 * The email is not verified yet: the user can browse and use the cart, but
 * the checkout is blocked by the verified middleware. Two simultaneous
 * registrations with the same email pass the unique rule: the second one
 * hits the unique index and gets the same 422 as the rule.
 */
final class RegisterUserAction
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'password_changed_at' => now(),
        ]);
        $user->role = UserRole::Customer;

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Questo indirizzo email è già in uso.']);
        }

        $user->sendEmailVerificationNotification();

        Auth::guard('web')->login($user);
        $this->request->session()->regenerate();

        return $user;
    }
}
