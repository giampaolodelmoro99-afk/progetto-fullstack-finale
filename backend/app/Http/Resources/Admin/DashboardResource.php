<?php

namespace App\Http\Resources\Admin;

use App\Actions\Admin\GetDashboardStatsAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The admin dashboard, from GetDashboardStatsAction: the month shown, the
 * indicators in cents and counts, the paid orders of the month by status,
 * the latest orders and the low stock products.
 *
 * @property array<string, mixed> $resource
 *
 * @see GetDashboardStatsAction
 */
final class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period' => ['month' => $this->resource['month']],
            'revenue_month' => $this->resource['revenue_month'],
            'orders_month' => $this->resource['orders_month'],
            'orders_to_ship' => $this->resource['orders_to_ship'],
            'low_stock_count' => $this->resource['low_stock_count'],
            'orders_by_status' => array_map(fn (array $row): array => [
                'status' => [
                    'value' => $row['status']->value,
                    'label' => $row['status']->label(),
                ],
                'count' => $row['count'],
            ], $this->resource['orders_by_status']),
            'latest_orders' => LatestOrderResource::collection($this->resource['latest_orders']),
            'low_stock_products' => LowStockProductResource::collection($this->resource['low_stock_products']),
        ];
    }
}
