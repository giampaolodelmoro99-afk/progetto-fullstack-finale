<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer of an order in the admin area: the only place that turns an
 * anonymized account into "Cliente eliminato" with a null email, because
 * the stored email is fictitious. The phone is not here: it belongs to the
 * recipient and stays in the address snapshot of the order.
 *
 * @mixin User
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->adminLabel(),
            'email' => $this->isAnonymized() ? null : $this->email,
        ];
    }
}
