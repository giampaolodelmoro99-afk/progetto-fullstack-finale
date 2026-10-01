<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Answer of the password change: only the date shown by the Security page.
 *
 * @mixin User
 */
final class PasswordChangedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'password_changed_at' => $this->password_changed_at->toIso8601String(),
        ];
    }
}
