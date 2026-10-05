<?php

namespace App\Http\Requests\Account;

use App\Support\Auth\PasskeyCredentialRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Name chosen by the user and answer of the browser to the registration
 * options (credential), checked by the WebAuthn library.
 */
final class StorePasskeyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            ...PasskeyCredentialRules::rules(['required']),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }
}
