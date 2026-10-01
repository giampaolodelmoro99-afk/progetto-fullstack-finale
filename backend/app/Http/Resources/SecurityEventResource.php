<?php

namespace App\Http\Resources;

use App\Models\SecurityEvent;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the recent activity. The raw user agent stays in the database:
 * the response carries only its label.
 *
 * @mixin SecurityEvent
 */
final class SecurityEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'device' => UserAgentLabel::for($this->user_agent),
            'ip_address' => $this->ip_address,
            'passkey_name' => $this->metadata['passkey_name'] ?? null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
