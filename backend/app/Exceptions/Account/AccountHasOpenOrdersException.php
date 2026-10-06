<?php

namespace App\Exceptions\Account;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account deletion asked with orders still in progress: paid, shipped or
 * with a payment in progress (Order::openForAccountDeletion).
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class AccountHasOpenOrdersException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'account_has_open_orders';

    public function __construct()
    {
        parent::__construct('Hai ordini ancora in corso: potrai eliminare l\'account quando saranno consegnati o annullati.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_CONFLICT);
    }
}
