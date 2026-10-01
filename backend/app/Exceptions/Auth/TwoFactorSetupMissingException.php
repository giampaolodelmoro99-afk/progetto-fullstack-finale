<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirmation of the activation without a secret waiting for it: the user
 * has to start the activation again.
 */
final class TwoFactorSetupMissingException extends RuntimeException
{
    public const CODE = 'two_factor_setup_missing';

    public function __construct()
    {
        parent::__construct('Avvia di nuovo l\'attivazione della doppia autenticazione.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_CONFLICT);
    }
}
