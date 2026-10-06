<?php

namespace App\Exceptions\Auth;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Password reset token that is wrong, expired, already used or tied to
 * another email address. The code lets the frontend tell it apart from a
 * field validation error and show "Il link è scaduto".
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class InvalidPasswordResetTokenException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'invalid_reset_token';

    public function __construct()
    {
        parent::__construct('Il link per reimpostare la password non è valido o è scaduto.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
