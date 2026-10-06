<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Support\LikePattern;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'number',
    'status',
    'subtotal',
    'shipping_cost',
    'total',
    'shipping_address',
    'terms_accepted_at',
    'paid_at',
    'shipped_at',
    'delivered_at',
    'cancelled_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'shipping_cost' => 'integer',
            'total' => 'integer',
            'shipping_address' => 'array',
            'terms_accepted_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * ORD-{year}-{id on 6 digits}, e.g. ORD-2026-000042.
     */
    public static function formatNumber(int $id, CarbonInterface $createdAt): string
    {
        return sprintf('ORD-%s-%06d', $createdAt->format('Y'), $id);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * The charge that went through, refunded afterwards or not: the payment
     * the customer sees. A rejected attempt is never shown to the customer.
     *
     * @return HasOne<Payment, $this>
     */
    public function capturedPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->whereIn('status', [PaymentStatus::Succeeded, PaymentStatus::Refunded]),
        );
    }

    /**
     * What the detail of OrderResource reads: rows with the current cover,
     * the captured payment, the history and the item count.
     */
    public function loadCustomerDetail(): static
    {
        return $this
            ->load(['items.product.cover', 'capturedPayment', 'statusChanges'])
            ->loadSum('items', 'quantity');
    }

    /**
     * Orders the customer can see: paid at least once.
     */
    #[Scope]
    protected function visibleToCustomer(Builder $query): void
    {
        $query->whereNotNull('paid_at');
    }

    /**
     * Rejected payment attempts: cancelled and never paid.
     */
    #[Scope]
    protected function failedPayments(Builder $query): void
    {
        $query->where('status', OrderStatus::Cancelled)->whereNull('paid_at');
    }

    /**
     * Orders that block the account deletion: paid or shipped, and pending
     * orders younger than account_deletion_pending_window minutes, whose
     * payment may still be in progress. An older pending order is an orphan.
     */
    #[Scope]
    protected function openForAccountDeletion(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereIn('status', [OrderStatus::Paid, OrderStatus::Shipped])
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', OrderStatus::Pending)
                    ->where('created_at', '>=', now()->subMinutes(config('security.account_deletion_pending_window'))));
        });
    }

    /**
     * Orders paid in the given month (Y-m), with the bounds in the application timezone.
     */
    #[Scope]
    protected function paidInMonth(Builder $query, string $month): void
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->startOfMonth();

        $query->whereBetween('paid_at', [$start, $start->endOfMonth()]);
    }

    /**
     * Order number or customer email containing the term.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $pattern = LikePattern::contains($term);

        $query->where(function (Builder $query) use ($pattern) {
            $query->where('number', 'like', $pattern)
                ->orWhereHas('user', fn (Builder $query) => $query->where('email', 'like', $pattern));
        });
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function canCancel(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === OrderStatus::Paid);
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function failedPayment(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->status === OrderStatus::Cancelled && $this->paid_at === null,
        );
    }
}
