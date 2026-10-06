<?php

namespace App\Http\Requests\Admin\Order;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Status change by the admin: only shipped, delivered and cancelled, because
 * paid and pending belong to the system. Whether the current status allows
 * the change is checked by the Actions (422 invalid_order_transition).
 */
final class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('order'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::adminTargets())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Puoi segnare un ordine solo come spedito, consegnato o annullato.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'stato',
        ];
    }

    public function targetStatus(): OrderStatus
    {
        return OrderStatus::from($this->validated('status'));
    }
}
