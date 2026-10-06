<?php

namespace App\Http\Resources\Admin;

use App\Enums\OrderStatus;
use App\Http\Resources\OrderItemResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The detail of an order in the admin area, rejected payment attempts
 * included: the fields of the list row, the fields of the customer detail,
 * the statuses the admin can set now, the latest payment (failed too) and
 * the history with author and reason. Loaded by Order::loadAdminDetail().
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
            ...OrderSummaryResource::make($this->resource)->toArray($request),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'can_cancel' => $this->can_cancel,
            'allowed_transitions' => array_map(
                fn (OrderStatus $status): string => $status->value,
                $this->status->adminTransitions(),
            ),
            'shipping_address' => $this->shipping_address,
            'payment' => $this->latestPayment === null ? null : PaymentResource::make($this->latestPayment),
            'items' => OrderItemResource::collection($this->items),
            'status_history' => OrderStatusChangeResource::collection($this->statusChanges),
        ];
    }
}
