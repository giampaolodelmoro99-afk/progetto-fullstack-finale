<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Answer of the email verification: only the confirmed address, so the page
 * shows which address was confirmed even if another user is logged in.
 *
 * @mixin User
 */
final class VerifiedEmailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
