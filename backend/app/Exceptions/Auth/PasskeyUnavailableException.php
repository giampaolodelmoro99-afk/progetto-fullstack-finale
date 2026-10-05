<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passkey options asked as second factor by a user who cannot use one: no
 * passkey registered, or two-factor authentication not active (then the
 * passkey is never asked as second factor).
 */
final class PasskeyUnavailableException extends RuntimeException
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
