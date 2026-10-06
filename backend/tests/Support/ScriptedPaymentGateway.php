<?php

namespace Tests\Support;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentResult;
use Closure;

/**
 * Payment gateway of the tests: counts the calls and answers with the
 * given callbacks, which can also act in between (delete a product, throw).
 * Without callbacks every charge and every refund succeeds.
 */
final class ScriptedPaymentGateway implements PaymentGateway
{
    public int $charges = 0;

    public int $refunds = 0;

    /**
     * @param  (Closure(Order, array<string, string>): PaymentResult)|null  $onCharge
     * @param  (Closure(Payment): PaymentResult)|null  $onRefund
     */
    public function __construct(
        private readonly ?Closure $onCharge = null,
        private readonly ?Closure $onRefund = null,
    ) {}

    public function charge(Order $order, #[\SensitiveParameter] array $paymentData): PaymentResult
    {
        $this->charges++;

        return $this->onCharge === null
            ? PaymentResult::success('test_charge', substr($paymentData['number'] ?? '4242', -4))
            : ($this->onCharge)($order, $paymentData);
    }

    public function refund(Payment $payment): PaymentResult
    {
        $this->refunds++;

        return $this->onRefund === null
            ? PaymentResult::success('test_refund', $payment->card_last_four)
            : ($this->onRefund)($payment);
    }
}
