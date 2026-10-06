<?php

namespace App\Http\Resources\Admin;

use App\Models\OrderStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the history as the admin sees it: the author (null = system,
 * "Cliente eliminato" for an anonymized customer), the reason and the
 * description with " da {name}".
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
            'description' => $this->description(withAuthor: true),
            'changed_by' => $this->changedBy === null ? null : [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->adminLabel(),
            ],
            'reason' => $this->reason,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
