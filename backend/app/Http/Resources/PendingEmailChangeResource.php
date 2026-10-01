<?php

namespace App\Http\Resources;

use App\Models\EmailChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A pending email change, without its token hashes.
 *
 * @mixin EmailChangeRequest
 */
final class PendingEmailChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'new_email' => $this->new_email,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
