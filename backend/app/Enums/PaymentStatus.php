<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Riuscito',
            self::Failed => 'Non riuscito',
            self::Refunded => 'Rimborsato',
        };
    }
}
