<?php

namespace App\Exceptions\Auth;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Activation asked while two-factor authentication is already active: a new
 * secret would silently replace the one in the authenticator app.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class TwoFactorAlreadyEnabledException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'two_factor_already_enabled';

    public function __construct()
    {
        parent::__construct('La doppia autenticazione è già attiva.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_CONFLICT);
    }
}
