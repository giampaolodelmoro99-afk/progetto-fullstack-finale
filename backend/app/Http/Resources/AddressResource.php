<?php

namespace App\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping address of the customer, always in Italy.
 *
 * @mixin Address
 */
final class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'street' => $this->street,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'province' => $this->province,
            'country' => $this->country,
            'phone' => $this->phone,
            'is_default' => $this->is_default,
        ];
    }
}
