<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shop settings for the shipping texts of the frontend, in cents, built on
 * the values of config/shop.php. The admin variant adds the low stock
 * threshold.
 *
 * @property array<string, int> $resource
 */
final class SettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'shipping_cost' => $this->resource['shipping_cost'],
            'free_shipping_threshold' => $this->resource['free_shipping_threshold'],
        ];
    }
}
