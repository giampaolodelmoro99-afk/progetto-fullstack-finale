<?php

namespace App\Exceptions\Auth;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passkey options asked as second factor by a user who cannot use one: no
 * passkey registered, or two-factor authentication not active (then the
 * passkey is never asked as second factor).
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class PasskeyUnavailableException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'passkey_unavailable';

    public function __construct()
    {
        parent::__construct('Nessuna passkey disponibile per questa verifica.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
