<?php

namespace App\Support\Auth;

use App\Rules\NotReservedEmailDomain;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Rules of a new account, shared by the registration of a customer
 * (RegisterRequest) and the creation of an admin (admin:create), so the
 * two never drift apart.
 */
final class RegistrationRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new NotReservedEmailDomain, Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.unique' => 'Questo indirizzo email è già in uso.',
        ];
    }

    /**
     * Trims the name, trims and lowercases the email; other values unchanged.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        if (is_string($data['name'] ?? null)) {
            $data['name'] = trim($data['name']);
        }

        if (is_string($data['email'] ?? null)) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        return $data;
    }
}
