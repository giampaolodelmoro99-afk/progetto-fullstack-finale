<?php

namespace App\Http\Requests\Admin\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the admin product list: name or SKU, category slug and
 * active state, with 10, 25 or 50 rows per page.
 */
final class IndexProductRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    public const DEFAULT_PER_PAGE = 10;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::exists('categories', 'slug')],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
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
            'status' => 'stato',
            'page' => 'pagina',
            'per_page' => 'righe per pagina',
        ];
    }

    /**
     * The validated filters in the format of Admin\SearchProductsAction.
     *
     * @return array{search?: string, category?: string, status?: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return array_filter([
            'search' => $validated['search'] ?? null,
            'category' => $validated['category'] ?? null,
            'status' => $validated['status'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PER_PAGE);
    }
}
