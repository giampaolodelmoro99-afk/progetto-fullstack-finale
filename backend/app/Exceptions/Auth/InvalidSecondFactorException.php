<?php

namespace App\Exceptions\Auth;

use App\Enums\SecondFactorMethod;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrong, reused or malformed second factor. The error is on the field of
 * the method (code, or credential for a passkey) and there is no top level
 * code: the frontend stays on the page and shows the message under the
 * field, or asks for the passkey again.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class InvalidSecondFactorException extends RuntimeException implements ShouldntReport
{
    public const MESSAGE = 'Codice non valido.';

    public const PASSKEY_MESSAGE = 'Passkey non valida: riprova.';

    public function __construct(
        string $message = self::MESSAGE,
        private readonly string $field = 'code',
    ) {
        parent::__construct($message);
    }

    public static function forMethod(SecondFactorMethod $method): self
    {
        return $method === SecondFactorMethod::Passkey
            ? new self(self::PASSKEY_MESSAGE, 'credential')
            : new self;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [$this->field => [$this->getMessage()]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
