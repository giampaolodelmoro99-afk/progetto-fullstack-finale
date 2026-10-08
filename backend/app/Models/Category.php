<?php

namespace App\Models;

use App\Observers\CategoryObserver;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description'])]
#[ObservedBy(CategoryObserver::class)]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * The slug a name produces, as CategoryObserver sets it on creation.
     */
    public static function slugFrom(string $name): string
    {
        return Str::slug($name) ?: 'categoria';
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Categories with at least one active product that is not soft deleted:
     * the public menu hides the empty ones.
     */
    #[Scope]
    protected function withActiveProducts(Builder $query): void
    {
        $query->whereHas('products', fn (Builder $products) => $products->where('is_active', true));
    }

    /**
     * Counts of the admin area: products_count (not deleted, active and
     * deactivated) and all_products_count (soft deleted included), which
     * decides whether the category can be deleted.
     */
    #[Scope]
    protected function withProductCounts(Builder $query): void
    {
        $query->withCount(self::productCounts());
    }

    /**
     * The same counts of withProductCounts() on a category already loaded.
     */
    public function loadProductCounts(): static
    {
        return $this->loadCount(self::productCounts());
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function productCounts(): array
    {
        return [
            'products',
            'products as all_products_count' => fn (Builder $query) => $query->withTrashed(),
        ];
    }
}
