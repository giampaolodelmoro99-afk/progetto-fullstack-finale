<?php

namespace App\Exceptions\Auth;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrong, reused or malformed second factor. The error is on the code field
 * and there is no top level code: the frontend stays on the page and shows
 * the message under the field.
 */
final class InvalidSecondFactorException extends RuntimeException
{
    public const MESSAGE = 'Codice non valido.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['code' => [$this->getMessage()]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
