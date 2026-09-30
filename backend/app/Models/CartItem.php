<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'quantity'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The row stays in the cart when the product is deactivated or soft deleted.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Active, not deleted and in stock.
     *
     * @return Attribute<bool, never>
     */
    protected function isPurchasable(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->product->is_active
                && ! $this->product->trashed()
                && $this->product->stock > 0,
        );
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function exceedsStock(): Attribute
    {
        return Attribute::get(fn (): bool => $this->quantity > $this->product->stock);
    }
}
