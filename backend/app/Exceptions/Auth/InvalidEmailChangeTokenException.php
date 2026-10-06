<?php

namespace App\Exceptions\Auth;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Email change token that is wrong, expired, already used or of a request
 * already cancelled. The code lets the frontend show "Il link è scaduto".
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class InvalidEmailChangeTokenException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'invalid_email_change_token';

    public function __construct()
    {
        parent::__construct('Il link per cambiare l\'indirizzo email non è valido o è scaduto.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
