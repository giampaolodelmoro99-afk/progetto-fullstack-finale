<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Paid => 'Pagato',
            self::Shipped => 'Spedito',
            self::Delivered => 'Consegnato',
            self::Cancelled => 'Annullato',
        };
    }

    /**
     * Whether the pair of states is allowed, regardless of who performs it.
     */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Paid, self::Cancelled], true),
            self::Paid => in_array($to, [self::Shipped, self::Cancelled], true),
            self::Shipped => $to === self::Delivered,
            self::Delivered, self::Cancelled => false,
        };
    }

    /**
     * The orders column that records when the order entered this state.
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Pending => null,
            self::Paid => 'paid_at',
            self::Shipped => 'shipped_at',
            self::Delivered => 'delivered_at',
            self::Cancelled => 'cancelled_at',
        };
    }
}
