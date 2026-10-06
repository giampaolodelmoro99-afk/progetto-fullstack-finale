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
     * The states the admin can set by hand; payment and creation belong to the system.
     *
     * @return list<self>
     */
    public static function adminTargets(): array
    {
        return [self::Shipped, self::Delivered, self::Cancelled];
    }

    /**
     * The states the admin can bring the order to from this one: paid →
     * shipped or cancelled, shipped → delivered, nothing otherwise. The admin
     * acts only on paid or shipped orders: pending → cancelled belongs to
     * the system.
     *
     * @return list<self>
     */
    public function adminTransitions(): array
    {
        if (! in_array($this, [self::Paid, self::Shipped], true)) {
            return [];
        }

        return array_values(array_filter(self::adminTargets(), fn (self $to): bool => $this->canTransitionTo($to)));
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
