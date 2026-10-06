<?php

namespace App\Exceptions\Assistant;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every concurrency slot is taken.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class AssistantBusyException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'assistant_busy';

    public function __construct()
    {
        parent::__construct("L'assistente è occupato, riprova tra poco.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
