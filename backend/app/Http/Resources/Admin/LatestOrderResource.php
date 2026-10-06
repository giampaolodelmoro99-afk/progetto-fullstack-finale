<?php

namespace App\Http\Resources\Admin;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order of "Ultimi ordini" in the dashboard: number, customer, date,
 * total and status.
 *
 * @mixin Order
 */
final class LatestOrderResource extends JsonResource
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
            'total' => $this->total,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
        ];
    }
}
