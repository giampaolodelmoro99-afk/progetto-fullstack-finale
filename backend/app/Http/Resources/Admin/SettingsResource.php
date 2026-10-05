<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shop settings of the admin area, in cents: the shipping values of the
 * shop plus the low stock threshold of dashboard, product list and form.
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
        ];
    }
}
