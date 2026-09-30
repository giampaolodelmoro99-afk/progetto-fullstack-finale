<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['from_status', 'to_status', 'changed_by', 'reason'])]
class OrderStatusChange extends Model
{
    /** @use HasFactory<OrderStatusChangeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The author of the transition; null means the system.
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Description of the transition. The admin variant adds " da {name}" when there is an author.
     */
    public function description(bool $withAuthor): string
    {
        $description = match ([$this->from_status, $this->to_status]) {
            [null, OrderStatus::Pending] => 'Ordine ricevuto, in attesa del pagamento',
            [OrderStatus::Pending, OrderStatus::Paid] => 'Pagamento con carta riuscito',
            [OrderStatus::Pending, OrderStatus::Cancelled] => $this->reason === null
                ? 'Pagamento non riuscito'
                : "Pagamento non riuscito: {$this->reason}",
            [OrderStatus::Paid, OrderStatus::Shipped] => 'Segnato come spedito',
            [OrderStatus::Shipped, OrderStatus::Delivered] => 'Segnato come consegnato',
            [OrderStatus::Paid, OrderStatus::Cancelled] => 'Annullato e rimborsato',
            default => throw new LogicException('Order status change without a description.'),
        };

        if ($withAuthor && $this->changed_by !== null) {
            $description .= " da {$this->changedBy->name}";
        }

        return $description;
    }
}
