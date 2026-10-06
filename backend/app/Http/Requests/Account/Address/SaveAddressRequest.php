<?php

namespace App\Http\Requests\Account\Address;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fields of an Italian shipping address, shared by creation and update.
 * The country is not among them: the server always stores IT. The province
 * code is trimmed and uppercased first, so «pi» is stored as «PI».
 */
abstract class SaveAddressRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    protected function fieldRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'province' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ]+$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postal_code.regex' => 'Il CAP deve avere 5 cifre.',
            'province.regex' => 'Usa la sigla di due lettere della provincia, per esempio PI.',
            'phone.regex' => 'Usa solo cifre, spazi e un + iniziale.',
        ];
    }

    /**
     * Only when sent: a partial update without the province leaves it out.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('province'))) {
            $this->merge(['province' => mb_strtoupper(trim($this->input('province')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'nome e cognome',
            'street' => 'indirizzo',
            'city' => 'città',
            'postal_code' => 'CAP',
            'province' => 'provincia',
            'phone' => 'telefono',
            'is_default' => 'indirizzo predefinito',
        ];
    }
}
