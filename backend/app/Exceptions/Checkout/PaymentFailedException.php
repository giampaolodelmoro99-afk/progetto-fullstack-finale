<?php

namespace App\Exceptions\Checkout;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Charge rejected by the gateway. The message is the text of the checkout
 * mockup; the reason of the gateway stays in the history of the order,
 * visible to the admin. Only the order reference, never card data.
 */
final class PaymentFailedException extends RuntimeException
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
