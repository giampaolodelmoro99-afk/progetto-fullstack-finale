<?php

namespace App\Exceptions\Assistant;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The model service cannot be reached, or the shop model is missing.
 * Raised before the answer starts streaming.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class AssistantUnavailableException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'assistant_unavailable';

    public function __construct()
    {
        parent::__construct("L'assistente non è disponibile in questo momento.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
