<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The link is valid, but the new address was registered by someone else
 * after the request: a conflict with the current state, so 409. The
 * request is cancelled, so the link cannot be tried again.
 */
final class EmailChangeUnavailableException extends RuntimeException
{
    public const CODE = 'email_change_unavailable';

    public function __construct()
    {
        parent::__construct('Questo indirizzo email è già in uso.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_CONFLICT);
    }
}
