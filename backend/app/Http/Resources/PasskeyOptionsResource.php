<?php

namespace App\Http\Resources;

use App\Support\Auth\PasskeyOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Options of a passkey ceremony in the JSON form of WebAuthn, ready for
 * startRegistration() or startAuthentication() of @simplewebauthn/browser.
 *
 * @mixin PasskeyOptions
 */
final class PasskeyOptionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'options' => $this->forBrowser,
        ];
    }
}
