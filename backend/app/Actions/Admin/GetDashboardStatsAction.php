<?php

namespace App\Actions\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Indicators and lists of the admin dashboard. Only orders paid at least
 * once count: rejected attempts and pending orders never enter. The
 * current month is taken in the application timezone and filtered with
 * the paidInMonth scope of the order list, so every count of
 * orders_by_status matches the list filtered by status and paid_month.
 */
final class GetDashboardStatsAction
{
    public const LATEST_ORDERS = 5;

    public const LOW_STOCK_PRODUCTS = 5;

    /**
     * Statuses of orders_by_status, in this order: a paid order is never pending.
     */
    public const MONTH_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
        OrderStatus::Cancelled,
    ];

    /**
     * @return array{
     *     month: string,
     *     revenue_month: int,
     *     orders_month: int,
     *     orders_to_ship: int,
     *     low_stock_count: int,
     *     orders_by_status: list<array{status: OrderStatus, count: int}>,
     *     latest_orders: Collection<int, Order>,
     *     low_stock_products: Collection<int, Product>,
     * }
     */
    public function execute(): array
    {
        $month = now()->timezone(config('app.timezone'))->format('Y-m');

        $counts = Order::query()
            ->paidInMonth($month)
            ->selectRaw('status, count(*) as orders_count')
            ->groupBy('status')
            ->pluck('orders_count', 'status');

        $ordersByStatus = array_map(fn (OrderStatus $status): array => [
            'status' => $status,
            'count' => (int) ($counts[$status->value] ?? 0),
        ], self::MONTH_STATUSES);

        return [
            'month' => $month,
            'revenue_month' => (int) Order::query()
                ->paidInMonth($month)
                ->where('status', '!=', OrderStatus::Cancelled)
                ->sum('total'),
            'orders_month' => (int) $counts->sum(),
            'orders_to_ship' => Order::query()->where('status', OrderStatus::Paid)->count(),
            'low_stock_count' => Product::query()->lowStock()->count(),
            'orders_by_status' => $ordersByStatus,
            'latest_orders' => Order::query()
                ->visibleToCustomer()
                ->with('user')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(self::LATEST_ORDERS)
                ->get(),
            'low_stock_products' => Product::query()
                ->lowStock()
                ->with('cover')
                ->orderBy('stock')
                ->orderBy('name')
                ->orderBy('id')
                ->limit(self::LOW_STOCK_PRODUCTS)
                ->get(),
        ];
    }
}
