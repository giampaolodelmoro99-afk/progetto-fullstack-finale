<?php

namespace App\Http\Requests\Account;

use App\Rules\NotReservedEmailDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The new address must differ from the current one and belong to nobody.
 * The uniqueness is checked again at the confirmation.
 */
final class RequestEmailChangeRequest extends FormRequest
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
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                new NotReservedEmailDomain,
                Rule::notIn([$this->user()->email]),
                Rule::unique('users', 'email'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.not_in' => 'Il nuovo indirizzo deve essere diverso da quello attuale.',
            'email.unique' => 'Questo indirizzo email è già in uso.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }
}
