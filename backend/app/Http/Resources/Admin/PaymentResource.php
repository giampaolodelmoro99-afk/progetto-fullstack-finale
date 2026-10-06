<?php

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The payment of an order in the admin area, failed attempts too: last four
 * digits, outcome, amount and date. The reason of a rejection is not here:
 * it is the reason of the cancelled row of the history.
 *
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'card_last_four' => $this->card_last_four,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'amount' => $this->amount,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
