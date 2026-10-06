<?php

namespace App\Exceptions\Checkout;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Charge rejected by the gateway. The message is the text of the checkout
 * mockup; the reason of the gateway stays in the history of the order,
 * visible to the admin. Only the order reference, never card data.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class PaymentFailedException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'payment_failed';

    public const MESSAGE = 'La carta è stata rifiutata e non ti è stato addebitato nulla. Controlla i dati della carta e riprova.';

    public function __construct(public readonly int $orderId)
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => self::MESSAGE,
            'code' => self::CODE,
        ], Response::HTTP_PAYMENT_REQUIRED);
    }
}
