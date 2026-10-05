<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Fields of the product form, shared by creation and update. The SKU is
 * unique among all products, soft deleted ones included, because order
 * snapshots keep it: the rule deliberately has no withoutTrashed().
 */
abstract class SaveProductRequest extends FormRequest
{
    abstract protected function uniqueSku(): Unique;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/', $this->uniqueSku()],
            'short_description' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'specifications' => ['present', 'list', 'max:20'],
            'specifications.*' => ['array:label,value'],
            'specifications.*.label' => ['required', 'string', 'max:64'],
            'specifications.*.value' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:1', 'max:999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'Usa solo lettere maiuscole, numeri e trattini',
            'sku.unique' => 'Questo SKU è già in uso',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'categoria',
            'name' => 'nome',
            'sku' => 'SKU',
            'short_description' => 'descrizione breve',
            'description' => 'descrizione',
            'specifications' => 'scheda tecnica',
            'specifications.*' => 'riga della scheda tecnica',
            'specifications.*.label' => 'voce',
            'specifications.*.value' => 'valore',
            'price' => 'prezzo',
            'stock' => 'stock',
            'is_active' => 'visibile nel negozio',
        ];
    }
}
