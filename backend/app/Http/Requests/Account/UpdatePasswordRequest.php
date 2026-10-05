<?php

namespace App\Http\Requests\Account;

use App\Enums\SecondFactorMethod;
use App\Support\Auth\PasskeyCredentialRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The second factor fields are optional here: whether they are needed
 * depends on the user, so ChangePasswordAction checks them.
 */
final class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'method' => ['nullable', 'string', Rule::enum(SecondFactorMethod::class)],
            'code' => ['nullable', 'string', 'max:64'],
            ...PasskeyCredentialRules::rules(['nullable']),
        ];
    }
}
