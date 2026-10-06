<?php

namespace App\Actions\Admin;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin order list: every order, rejected payment attempts and pending
 * orders included, newest first and then by id. The month of payment uses
 * the paidInMonth scope of the dashboard, so the two never diverge.
 * Returns the query, so the caller paginates it.
 */
final class SearchOrdersAction
{
    /**
     * @param  array{search?: string, status?: string, failed_payment?: bool, paid_month?: string}  $filters  validated filters
     * @return Builder<Order>
     */
    public function execute(array $filters): Builder
    {
        return Order::query()
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->search($filters['search']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['failed_payment'] ?? false, fn (Builder $query) => $query->failedPayments())
            ->when(isset($filters['paid_month']), fn (Builder $query) => $query->paidInMonth($filters['paid_month']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
