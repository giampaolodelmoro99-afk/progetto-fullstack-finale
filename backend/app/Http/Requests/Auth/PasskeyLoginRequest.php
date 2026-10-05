<?php

namespace App\Http\Requests\Auth;

use App\Support\Auth\PasskeyCredentialRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Answer of the browser to the options of the passwordless login,
 * checked by the WebAuthn library.
 */
final class PasskeyLoginRequest extends FormRequest
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
            ...PasskeyCredentialRules::rules(['required']),
        ];
    }
}
