<?php

namespace App\Http\Requests\Account;

use App\Enums\SecondFactorMethod;
use App\Support\Auth\PasskeyCredentialRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The second factor fields are optional here: whether they are needed
 * depends on the user, so ConfirmIdentityAction checks them.
 */
final class ConfirmIdentityRequest extends FormRequest
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
            'password' => ['required', 'string', 'current_password:web'],
            'method' => ['nullable', 'string', Rule::enum(SecondFactorMethod::class)],
            'code' => ['nullable', 'string', 'max:64'],
            ...PasskeyCredentialRules::rules(['nullable']),
        ];
    }
}
