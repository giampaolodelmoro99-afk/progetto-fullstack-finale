<?php

namespace App\Exceptions\Auth;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrong email or password. Never says which one, and carries no field
 * errors, so the frontend shows a single notice.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class InvalidCredentialsException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'invalid_credentials';

    public function __construct()
    {
        parent::__construct('Credenziali non valide');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
