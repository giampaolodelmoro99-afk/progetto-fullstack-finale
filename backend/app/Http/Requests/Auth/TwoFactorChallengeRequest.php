<?php

namespace App\Http\Requests\Auth;

use App\Enums\SecondFactorMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'code' => ['required', 'string', 'max:64'],
        ];
    }
}
