<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A customer sees and cancels only the own orders paid at least once:
 * orders of somebody else, rejected payment attempts and pending orders
 * answer 404, so their existence is never revealed. The admin sees every
 * order, rejected attempts included, and changes the status by hand.
 * Whether the order can still be cancelled or moved on is a matter of its
 * status, checked by the Actions (422).
 */
final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Order $order): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return $this->visibleToOwner($user, $order);
    }

    public function cancel(User $user, Order $order): Response
    {
        return $this->visibleToOwner($user, $order);
    }

    private function visibleToOwner(User $user, Order $order): Response
    {
        return (int) $order->user_id === $user->id && $order->paid_at !== null
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
