<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order of the customer. The list loads only the item count; the detail
 * adds rows, payment and history. The payment shows only the last four
 * digits of a succeeded or refunded charge; the history has no author and
 * no reason. can_cancel depends on the status only.
 *
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'total' => $this->total,
            'items_count' => $this->whenAggregated('items', 'quantity', 'sum', fn ($sum): int => (int) $sum),
            'can_cancel' => $this->can_cancel,
            'shipping_address' => $this->shipping_address,
            'payment' => $this->whenLoaded('capturedPayment', fn (): ?array => $this->capturedPayment === null
                ? null
                : ['card_last_four' => $this->capturedPayment->card_last_four]),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_history' => OrderStatusChangeResource::collection($this->whenLoaded('statusChanges')),
        ];
    }
}
