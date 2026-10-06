<?php

namespace App\Http\Resources\Admin;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the admin order list, rejected payment attempts included:
 * customer, item count (sum of the quantities) and failed_payment, with the
 * same name as the filter.
 *
 * @mixin Order
 */
final class OrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer' => CustomerResource::make($this->user),
            'created_at' => $this->created_at->toIso8601String(),
            'items_count' => (int) $this->items_sum_quantity,
            'total' => $this->total,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'failed_payment' => $this->failed_payment,
        ];
    }
}
