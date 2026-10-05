<?php

namespace App\Http\Requests\Auth;

use App\Enums\SecondFactorMethod;
use App\Support\Auth\PasskeyCredentialRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The code of the app or a recovery code, or the answer of the browser to
 * the passkey options (credential), checked by the WebAuthn library.
 */
final class TwoFactorChallengeRequest extends FormRequest
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
            'method' => ['required', 'string', Rule::enum(SecondFactorMethod::class)],
            'code' => ['required_unless:method,passkey', 'nullable', 'string', 'max:64'],
            ...PasskeyCredentialRules::rules(['required_if:method,passkey', 'nullable']),
        ];
    }
}
