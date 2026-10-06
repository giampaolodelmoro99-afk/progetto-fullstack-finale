<?php

namespace App\Exceptions\Orders;

use App\Enums\OrderStatus;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A change of status that the current status does not allow, e.g. the
 * cancellation of a shipped order.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class InvalidOrderTransitionException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'invalid_order_transition';

    public const MESSAGE = 'Lo stato attuale dell\'ordine non consente questa operazione.';

    public function __construct(public readonly OrderStatus $from, public readonly OrderStatus $to)
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => self::MESSAGE,
            'code' => self::CODE,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
