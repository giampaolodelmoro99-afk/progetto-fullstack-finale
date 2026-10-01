<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recovery codes in clear, shown once after the activation or a
 * regeneration. The resource is the list of codes.
 */
final class RecoveryCodesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'recovery_codes' => $this->resource,
        ];
    }

    /**
     * Secrets in clear: never kept by the browser or a proxy cache.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store');
    }
}
