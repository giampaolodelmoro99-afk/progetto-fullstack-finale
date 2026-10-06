<?php

namespace App\Exceptions\Auth;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recovery codes regenerated or two-factor authentication disabled while it
 * is not active.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class TwoFactorNotEnabledException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'two_factor_not_enabled';

    public function __construct()
    {
        parent::__construct('La doppia autenticazione non è attiva.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_CONFLICT);
    }
}
