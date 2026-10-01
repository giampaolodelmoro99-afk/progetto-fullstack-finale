<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Email verification link for a missing user or for an email address that
 * is no longer the current one. The invalid signature of the signed
 * middleware answers with the same message, so the cases look identical.
 */
final class InvalidVerificationLinkException extends RuntimeException
{
    public const MESSAGE = 'Il link di verifica non è valido o è scaduto.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], Response::HTTP_FORBIDDEN);
    }
}
