<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passwordless login with a passkey that does not pass: options missing,
 * used or expired, unknown credential, wrong signature or origin, missing
 * user verification, anonymized account. One answer for every case and no
 * field errors, as for wrong credentials: the frontend shows a single
 * notice. No login_failed event: without a valid passkey the user is not
 * reliably identified.
 */
final class InvalidPasskeyException extends RuntimeException
{
    public const CODE = 'invalid_passkey';

    public function __construct()
    {
        parent::__construct('Passkey non riconosciuta: riprova o accedi con la password.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
