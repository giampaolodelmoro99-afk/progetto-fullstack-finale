<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Availability of the assistant for the widget.
 *
 * @property bool $resource
 */
final class AssistantStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'available' => $this->resource,
        ];
    }
}
