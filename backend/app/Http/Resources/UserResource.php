<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user. Never exposes password, tokens or two-factor
 * secrets. pending_email_change needs the pendingEmailChange relation loaded.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'role' => [
                'value' => $this->role->value,
                'label' => $this->role->label(),
            ],
            'pending_email_change' => $this->whenLoaded('pendingEmailChange', fn (): ?array => $this->pendingEmailChange === null ? null : [
                'new_email' => $this->pendingEmailChange->new_email,
                'expires_at' => $this->pendingEmailChange->expires_at->toIso8601String(),
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
