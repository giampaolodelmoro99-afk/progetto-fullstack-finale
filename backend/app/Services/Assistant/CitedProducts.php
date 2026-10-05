<?php

namespace App\Services\Assistant;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Products cited by the model with the [[slug]] marker, shown as cards.
 *
 * Slugs are taken in order of citation without duplicates, capped before
 * the query; products that cannot be bought (missing, deactivated, deleted
 * or out of stock) are dropped before keeping the first max_products, so an
 * invalid citation never takes the place of a valid card.
 */
final class CitedProducts
{
    /**
     * Upper bound on the slugs looked up for one answer.
     */
    public const MAX_SLUGS = 20;

    private const MARKER = '/\[\[([a-z0-9]+(?:-[a-z0-9]+)*)\]\]/';

    /**
     * @return Collection<int, Product>
     */
    public function fromText(string $text): Collection
    {
        $slugs = $this->slugs($text);

        if ($slugs === []) {
            return new Collection;
        }

        $position = array_flip($slugs);

        return Product::query()
            ->active()
            ->inStock()
            ->whereIn('slug', $slugs)
            ->with(['category', 'cover'])
            ->get()
            ->sortBy(fn (Product $product): int => $position[$product->slug])
            ->take(config('assistant.max_products'))
            ->values();
    }

    /**
     * @return list<string>
     */
    private function slugs(string $text): array
    {
        preg_match_all(self::MARKER, $text, $matches);

        return array_slice(array_values(array_unique($matches[1])), 0, self::MAX_SLUGS);
    }
}
