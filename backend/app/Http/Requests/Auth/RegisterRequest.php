<?php

namespace App\Http\Requests\Auth;

use App\Support\Auth\RegistrationRules;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
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
        return RegistrationRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RegistrationRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(RegistrationRules::normalize($this->only(['name', 'email'])));
    }
}
