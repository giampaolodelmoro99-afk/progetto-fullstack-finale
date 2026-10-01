<?php

namespace App\Http\Resources;

use App\Support\Auth\IdentityConfirmation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IdentityConfirmation
 */
final class IdentityConfirmationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'confirmed_until' => $this->confirmedUntil->toIso8601String(),
        ];
    }
}
