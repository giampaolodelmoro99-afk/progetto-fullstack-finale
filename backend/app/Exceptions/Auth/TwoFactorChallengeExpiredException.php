<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The login challenge is missing or older than login_challenge_ttl. It is a
 * 422 with a code, not a 419, which belongs to CSRF and is retried by the
 * frontend interceptor: here the frontend goes back to the login.
 */
final class TwoFactorChallengeExpiredException extends RuntimeException
{
    public const CODE = 'two_factor_challenge_expired';

    public function __construct()
    {
        parent::__construct('La verifica è scaduta: accedi di nuovo.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
