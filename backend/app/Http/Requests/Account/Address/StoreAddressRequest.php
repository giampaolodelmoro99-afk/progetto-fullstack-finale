<?php

namespace App\Http\Requests\Account\Address;

use App\Models\Address;

final class StoreAddressRequest extends SaveAddressRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Address::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->fieldRules();
    }
}
