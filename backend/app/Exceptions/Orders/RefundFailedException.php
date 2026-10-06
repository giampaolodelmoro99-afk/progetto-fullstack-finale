<?php

namespace App\Exceptions\Orders;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gateway refused the refund: the order was not cancelled and nothing
 * changed (status, stock, payment, history).
 */
final class RefundFailedException extends RuntimeException
{
    public const CODE = 'refund_failed';

    public const MESSAGE = 'Il rimborso non è riuscito: l\'ordine non è stato annullato. Riprova tra poco.';

    public function __construct(public readonly int $orderId)
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => self::MESSAGE,
            'code' => self::CODE,
        ], Response::HTTP_BAD_GATEWAY);
    }
}
