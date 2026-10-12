<?php

namespace App\Http\Resources\Admin;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shop settings of the admin area, in cents: the shipping values of the
 * shop plus the low stock threshold of dashboard, product list and form,
 * and the order states with their labels for the filter of the order list.
 * The resource holds only the shop configuration: the order states come
 * from the OrderStatus enum.
 *
 * @property array<string, int> $resource
 */
final class SettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'shipping_cost' => $this->resource['shipping_cost'],
            'free_shipping_threshold' => $this->resource['free_shipping_threshold'],
            'low_stock_threshold' => $this->resource['low_stock_threshold'],
            'order_statuses' => array_map(
                fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                OrderStatus::cases(),
            ),
        ];
    }
}
