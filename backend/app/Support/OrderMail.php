<?php

namespace App\Support;

use App\Models\Order;

/**
 * Shared pieces of the order emails: the page of the order in the frontend
 * and the snapshot of the shipping address on one line.
 */
final class OrderMail
{
    public const VIEW_ORDER = 'Vedi l\'ordine';

    public static function url(Order $order): string
    {
        return config('app.frontend_url').'/account/orders/'.$order->id;
    }

    /**
     * Giulia Ferri, Via San Martino 42, 56125 Pisa (PI), Italia
     */
    public static function address(Order $order): string
    {
        $address = $order->shipping_address;

        return "{$address['full_name']}, {$address['street']}, {$address['postal_code']} {$address['city']} ({$address['province']}), Italia";
    }
}
