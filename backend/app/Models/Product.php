<?php

namespace App\Models;

use App\Observers\ProductObserver;
use App\Support\LikePattern;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'category_id',
    'name',
    'sku',
    'short_description',
    'description',
    'specifications',
    'price',
    'stock',
    'is_active',
])]
#[ObservedBy(ProductObserver::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stock' => 0,
        'is_active' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'price' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The first image by position.
     *
     * @return HasOne<ProductImage, $this>
     */
    public function cover(): HasOne
    {
        return $this->hasOne(ProductImage::class)->oldestOfMany('position');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function inStock(Builder $query): void
    {
        $query->where('stock', '>', 0);
    }

    #[Scope]
    protected function newArrivals(Builder $query): void
    {
        $query->where('created_at', '>=', now()->subDays(config('shop.new_arrival_days')));
    }

    /**
     * Public catalog search: name and short description.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $pattern = LikePattern::contains($term);

        $query->where(function (Builder $query) use ($pattern) {
            $query->where('name', 'like', $pattern)
                ->orWhere('short_description', 'like', $pattern);
        });
    }

    /**
     * Admin search: name and SKU.
     */
    #[Scope]
    protected function searchByNameOrSku(Builder $query, string $term): void
    {
        $pattern = LikePattern::contains($term);

        $query->where(function (Builder $query) use ($pattern) {
            $query->where('name', 'like', $pattern)
                ->orWhere('sku', 'like', $pattern);
        });
    }

    #[Scope]
    protected function inCategory(Builder $query, string $slug): void
    {
        $query->whereHas('category', fn (Builder $query) => $query->where('slug', $slug));
    }

    #[Scope]
    protected function priceBetween(Builder $query, ?int $min, ?int $max): void
    {
        $query->when($min !== null, fn (Builder $query) => $query->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $query) => $query->where('price', '<=', $max));
    }

    /**
     * Sorts by newest, price_asc or price_desc, with the id as tie-breaker.
     */
    #[Scope]
    protected function sortBy(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderBy('id'),
            default => throw new InvalidArgumentException("Unsupported product sort [{$sort}]."),
        };
    }

    /**
     * Active products at or below the admin low stock threshold, out of stock included.
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('stock', '<=', config('shop.low_stock_threshold'));
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isNew(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->created_at !== null
                && $this->created_at->greaterThanOrEqualTo(now()->subDays(config('shop.new_arrival_days'))),
        );
    }

    /**
     * Availability shown to customers.
     *
     * @return Attribute<array{status: string, label: string}, never>
     */
    protected function availability(): Attribute
    {
        return Attribute::get(fn (): array => $this->stockLevel(config('shop.last_units_threshold')));
    }

    /**
     * Availability shown in the admin area, with the restocking threshold.
     *
     * @return Attribute<array{status: string, label: string}, never>
     */
    protected function stockStatus(): Attribute
    {
        return Attribute::get(fn (): array => $this->stockLevel(config('shop.low_stock_threshold')));
    }

    /**
     * @return Attribute<int, never>
     */
    protected function maxQuantity(): Attribute
    {
        return Attribute::get(fn (): int => min($this->stock, config('shop.max_quantity_per_item')));
    }

    /**
     * @return array{status: string, label: string}
     */
    private function stockLevel(int $threshold): array
    {
        return match (true) {
            $this->stock <= 0 => ['status' => 'out_of_stock', 'label' => 'Esaurito'],
            $this->stock === 1 => ['status' => 'low_stock', 'label' => 'Ultimo pezzo'],
            $this->stock <= $threshold => ['status' => 'low_stock', 'label' => "Ultimi {$this->stock} pezzi"],
            default => ['status' => 'available', 'label' => 'Disponibile'],
        };
    }
}
