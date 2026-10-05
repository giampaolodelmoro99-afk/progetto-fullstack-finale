<?php

namespace App\Http\Requests\Account;

use App\Models\Passkey;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Rename of a passkey: only the own ones (PasskeyPolicy, 404 otherwise).
 */
final class UpdatePasskeyRequest extends FormRequest
{
    public function authorize(): Response
    {
        $passkey = $this->route('passkey');

        return $passkey instanceof Passkey ? Gate::inspect('update', $passkey) : Response::denyAsNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }
}
