<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Simulated gateway, the default driver (PAYMENT_DRIVER=fake). It approves
 * every card except those ending in 0002, rejected as by the bank to show
 * the error case in the demo. Refunds always succeed; the tests that need a
 * failed refund replace the gateway in the container.
 *
 * Every result is kept in the cache under its idempotency key (order id for
 * the charge, payment id for the refund): a repeated call returns the same
 * result without a second movement, as a real provider does.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public const PROVIDER = 'fake';

    public const DECLINED_SUFFIX = '0002';

    public const DECLINED_REASON = 'Carta rifiutata dalla banca';

    /**
     * Reason of a charge repeated without card data and never seen before:
     * the checkout stopped before reaching the gateway.
     */
    public const NOT_RECEIVED_REASON = 'Pagamento non ricevuto dal servizio di pagamento';

    public function charge(Order $order, #[\SensitiveParameter] array $paymentData): PaymentResult
    {
        $key = self::chargeKey($order->id);
        $stored = Cache::get($key);

        if (is_array($stored)) {
            return PaymentResult::fromArray($stored);
        }

        $number = $paymentData['number'] ?? null;

        if ($number === null) {
            return PaymentResult::failure(self::NOT_RECEIVED_REASON);
        }

        $lastFour = substr($number, -4);
        $result = $lastFour === self::DECLINED_SUFFIX
            ? PaymentResult::failure(self::DECLINED_REASON, $lastFour)
            : PaymentResult::success(self::reference('ch'), $lastFour);

        Cache::forever($key, $result->toArray());

        return $result;
    }

    public function refund(Payment $payment): PaymentResult
    {
        $key = self::refundKey($payment->id);
        $stored = Cache::get($key);

        if (is_array($stored)) {
            return PaymentResult::fromArray($stored);
        }

        $result = PaymentResult::success(self::reference('re'), $payment->card_last_four);

        Cache::forever($key, $result->toArray());

        return $result;
    }

    public static function chargeKey(int $orderId): string
    {
        return "fake-payments:charge:{$orderId}";
    }

    public static function refundKey(int $paymentId): string
    {
        return "fake-payments:refund:{$paymentId}";
    }

    private static function reference(string $prefix): string
    {
        return "fake_{$prefix}_".Str::random(24);
    }
}
