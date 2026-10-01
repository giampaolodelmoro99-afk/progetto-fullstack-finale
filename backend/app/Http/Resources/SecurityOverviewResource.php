<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Summary of the Security page. Without two-factor authentication the
 * recovery code counts are 0. Passkeys arrive in Phase 3c: until then the
 * count is 0.
 *
 * @mixin User
 */
final class SecurityOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'two_factor_enabled' => $this->hasSecondFactor(),
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at?->toIso8601String(),
            'recovery_codes_remaining' => $this->recovery_codes_remaining,
            'recovery_codes_total' => $this->hasSecondFactor() ? config('security.recovery_codes_count') : 0,
            'passkeys_count' => 0,
            'email_verified' => $this->hasVerifiedEmail(),
            'password_changed_at' => $this->password_changed_at?->toIso8601String(),
        ];
    }
}
