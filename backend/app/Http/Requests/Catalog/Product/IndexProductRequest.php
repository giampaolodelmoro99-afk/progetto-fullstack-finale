<?php

namespace App\Http\Requests\Catalog\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the public catalog. Prices are in cents; in_stock and new
 * accept 1, 0, true and false, the forms a query string usually carries.
 */
final class IndexProductRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [4, 12, 24, 48];

    public const DEFAULT_PER_PAGE = 12;

    private const BOOLEAN_FILTERS = ['in_stock', 'new'];

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
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::exists('categories', 'slug')],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'max_price' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999',
                Rule::when(is_numeric($this->input('min_price')), 'gte:min_price'),
            ],
            'in_stock' => ['nullable', 'boolean'],
            'new' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.exists' => 'Questa categoria non esiste.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'search' => 'ricerca',
            'category' => 'categoria',
            'min_price' => 'prezzo minimo',
            'max_price' => 'prezzo massimo',
            'in_stock' => 'solo disponibili',
            'new' => 'novità',
            'sort' => 'ordinamento',
            'page' => 'pagina',
            'per_page' => 'prodotti per pagina',
        ];
    }

    /**
     * The validated filters in the format of SearchProductsAction.
     *
     * @return array{search?: string, category?: string, min_price?: int, max_price?: int, in_stock?: bool, new?: bool, sort?: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return array_filter([
            'search' => $validated['search'] ?? null,
            'category' => $validated['category'] ?? null,
            'min_price' => isset($validated['min_price']) ? (int) $validated['min_price'] : null,
            'max_price' => isset($validated['max_price']) ? (int) $validated['max_price'] : null,
            'in_stock' => isset($validated['in_stock']) ? $this->boolean('in_stock') : null,
            'new' => isset($validated['new']) ? $this->boolean('new') : null,
            'sort' => $validated['sort'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PER_PAGE);
    }

    protected function prepareForValidation(): void
    {
        foreach (self::BOOLEAN_FILTERS as $key) {
            $value = $this->input($key);

            if (is_string($value) && in_array(mb_strtolower($value), ['true', 'false'], true)) {
                $this->merge([$key => mb_strtolower($value) === 'true' ? '1' : '0']);
            }
        }
    }
}
