<?php

namespace App\Exceptions\Checkout;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checkout with an empty cart. The frontend disables "Paga" in that case;
 * the backend checks again and answers with a conflict with the current
 * state, distinguished from the changed availability by its code.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class EmptyCartException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'cart_empty';

    public const MESSAGE = 'Il carrello è vuoto.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => self::MESSAGE,
            'code' => self::CODE,
        ], Response::HTTP_CONFLICT);
    }
}
