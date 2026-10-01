<?php

namespace App\Http\Resources;

use App\Support\Auth\TwoFactorSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The secret waiting for confirmation, the only response that shows it:
 * key for manual entry, otpauth URI and QR code as SVG.
 *
 * @mixin TwoFactorSetup
 */
final class TwoFactorSetupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'secret' => $this->secret,
            'otpauth_url' => $this->otpauthUrl,
            'qr_code_svg' => $this->qrCodeSvg,
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
