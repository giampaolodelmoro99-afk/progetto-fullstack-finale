<?php

namespace App\Http\Requests\Account\Address;

use App\Models\Address;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Partial update: "Imposta come predefinito" sends only is_default, the
 * edit form sends every field. A field that is sent follows the same rules
 * as in the creation. The address of somebody else answers 404.
 */
final class UpdateAddressRequest extends SaveAddressRequest
{
    public function authorize(): Response
    {
        $address = $this->route('address');

        return $address instanceof Address ? Gate::inspect('update', $address) : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_map(
            fn (array $rules): array => in_array('sometimes', $rules, true) ? $rules : ['sometimes', ...$rules],
            $this->fieldRules(),
        );
    }
}
