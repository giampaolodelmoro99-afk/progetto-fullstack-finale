<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentResult;

/**
 * Card payments. Calls to an external service: never inside a database
 * transaction. Each call carries an idempotency key, so repeating it never
 * moves money twice: the order id for the charge (every attempt creates a
 * new order), the payment id for the refund.
 */
interface PaymentGateway
{
    /**
     * Charges the total of the order. A repeated call with the same order
     * returns the result of the first one; without card data (the
     * reconciliation of an interrupted checkout) only that stored result can
     * be returned.
     *
     * @param  array{holder?: string, number?: string, expiry?: string, cvc?: string}  $paymentData
     */
    public function charge(Order $order, #[\SensitiveParameter] array $paymentData): PaymentResult;

    /**
     * Refunds a succeeded payment. A repeated call with the same payment
     * returns the result of the first one.
     */
    public function refund(Payment $payment): PaymentResult;
}
