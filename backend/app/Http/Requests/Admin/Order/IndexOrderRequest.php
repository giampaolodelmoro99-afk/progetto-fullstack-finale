<?php

namespace App\Http\Requests\Admin\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the admin order list: order number or customer email, status,
 * rejected payment attempts and month of payment (Y-m, the month of the
 * dashboard), with 10, 25 or 50 rows per page. The filters combine in AND.
 */
final class IndexOrderRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    public const DEFAULT_PER_PAGE = 10;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::enum(OrderStatus::class)],
            'failed_payment' => ['nullable', Rule::in(['1'])],
            'paid_month' => ['nullable', 'string', 'date_format:Y-m'],
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
            'paid_month.date_format' => 'Indica il mese nel formato AAAA-MM, per esempio 2026-09.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'search' => 'ricerca',
            'status' => 'stato',
            'failed_payment' => 'pagamenti non riusciti',
            'paid_month' => 'mese di pagamento',
            'page' => 'pagina',
            'per_page' => 'righe per pagina',
        ];
    }

    /**
     * The validated filters in the format of Admin\SearchOrdersAction.
     *
     * @return array{search?: string, status?: string, failed_payment?: bool, paid_month?: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return array_filter([
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'failed_payment' => isset($validated['failed_payment']) ? true : null,
            'paid_month' => $validated['paid_month'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PER_PAGE);
    }
}
