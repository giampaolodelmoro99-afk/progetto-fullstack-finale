<?php

namespace App\Http\Resources;

use App\Models\OrderStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the history as the customer sees it: the reached status and the
 * description, never the author or the reason.
 *
 * @mixin OrderStatusChange
 */
final class OrderStatusChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => [
                'value' => $this->to_status->value,
                'label' => $this->to_status->label(),
            ],
            'description' => $this->description(withAuthor: false),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
