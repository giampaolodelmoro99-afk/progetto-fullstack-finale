<?php

namespace App\Services\Assistant;

use App\Actions\Catalog\SearchProductsAction;
use App\Actions\Shop\GetSupportSectionsAction;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;

/**
 * Tools offered to the model: catalog search, product detail and the shop
 * rules of the support page. They are the only source of shop data for the
 * model. Arguments proposed by the model are validated like user input:
 * invalid values are dropped, never turned into a query.
 */
final class AssistantTools
{
    public const SEARCH_PRODUCTS = 'search_products';

    public const GET_PRODUCT = 'get_product';

    public const GET_SHOP_INFO = 'get_shop_info';

    private const SORTS = ['newest', 'price_asc', 'price_desc'];

    public function __construct(
        private readonly SearchProductsAction $searchProducts,
        private readonly GetSupportSectionsAction $supportSections,
    ) {}

    /**
     * Tool definitions for /api/chat. The category slugs are read from the
     * database at every call, so the model can only propose existing ones.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $category = ['type' => 'string', 'description' => 'Slug della categoria.'];
        $slugs = Category::query()->orderBy('id')->pluck('slug')->all();

        if ($slugs !== []) {
            $category['enum'] = $slugs;
        }

        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => self::SEARCH_PRODUCTS,
                    'description' => 'Cerca i prodotti del catalogo di Casa del Silicio. Restituisce nome, slug, categoria, prezzo, disponibilità e descrizione breve, e in total il numero dei prodotti trovati.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'search' => ['type' => 'string', 'description' => 'Una sola parola da cercare nel nome e nella descrizione, per esempio "cuffie". Usala sempre quando il cliente nomina un tipo di prodotto (caricatore, cuffie, radio): cercare solo per categoria restituisce al massimo '.config('assistant.max_tool_results').' prodotti. Per due tipi di prodotto fai due ricerche.'],
                            'category' => $category,
                            'min_price' => ['type' => 'number', 'description' => 'Prezzo minimo in euro. Solo per un prezzo detto dal cliente, mai per watt, capacità o altre caratteristiche: quelle si leggono nei risultati.'],
                            'max_price' => ['type' => 'number', 'description' => 'Prezzo massimo in euro. Solo per un prezzo detto dal cliente, mai per watt, capacità o altre caratteristiche.'],
                            'in_stock' => ['type' => 'boolean', 'description' => 'Solo prodotti disponibili.'],
                            'new' => ['type' => 'boolean', 'description' => 'Solo novità degli ultimi giorni.'],
                            'sort' => ['type' => 'string', 'enum' => self::SORTS, 'description' => 'Ordinamento: newest, price_asc o price_desc.'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => self::GET_PRODUCT,
                    'description' => 'Dettaglio di un prodotto con descrizione breve e scheda tecnica.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'description' => 'Slug del prodotto ricevuto da search_products.'],
                        ],
                        'required' => ['slug'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => self::GET_SHOP_INFO,
                    'description' => 'Regole del negozio: spedizioni, resi, garanzia e contatti. Senza sezione restituisce tutte le regole.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section' => ['type' => 'string', 'enum' => $this->sectionIds(), 'description' => 'Sezione richiesta.'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Runs a tool and returns the result sent back to the model.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments): array
    {
        return match ($name) {
            self::SEARCH_PRODUCTS => $this->searchProducts($arguments),
            self::GET_PRODUCT => $this->getProduct($arguments),
            self::GET_SHOP_INFO => $this->getShopInfo($arguments),
            default => ['error' => 'Strumento non disponibile.'],
        };
    }

    /**
     * At most max_tool_results products, with the total of the matching
     * ones. When the list is partial, the note states only the fact: tool
     * results are data, never instructions, and what to do about a partial
     * list is in the Modelfile.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{products: list<array<string, mixed>>, total: int, note?: string}
     */
    private function searchProducts(array $arguments): array
    {
        $query = $this->searchProducts->execute($this->searchFilters($arguments));
        $total = (clone $query)->count();

        $products = $query
            ->with('category')
            ->limit(config('assistant.max_tool_results'))
            ->get();

        $result = [
            'products' => $products->map(fn (Product $product): array => $this->summary($product))->all(),
            'total' => $total,
        ];

        if ($total > $products->count()) {
            $result['note'] = "Mostrati {$products->count()} prodotti su {$total}.";
        }

        return $result;
    }

    /**
     * Keeps only the valid arguments, with prices converted to cents.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{search?: string, category?: string, min_price?: int, max_price?: int, in_stock?: bool, new?: bool, sort?: string}
     */
    private function searchFilters(array $arguments): array
    {
        $rules = [
            'search' => ['string', 'max:100'],
            'category' => ['string', 'max:100'],
            'min_price' => ['numeric', 'min:0', 'max:9999.99'],
            'max_price' => ['numeric', 'min:0', 'max:9999.99'],
            'sort' => ['string', 'in:'.implode(',', self::SORTS)],
        ];

        $filters = [];

        foreach ($rules as $key => $keyRules) {
            $value = $arguments[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if (blank($value) || Validator::make([$key => $value], [$key => $keyRules])->fails()) {
                continue;
            }

            $filters[$key] = in_array($key, ['min_price', 'max_price'], true)
                ? (int) round((float) $value * 100)
                : $value;
        }

        if (isset($filters['category']) && ! Category::query()->where('slug', $filters['category'])->exists()) {
            unset($filters['category']);
        }

        foreach (['in_stock', 'new'] as $key) {
            $value = filter_var($arguments[$key] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($value !== null) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getProduct(array $arguments): array
    {
        $slug = $arguments['slug'] ?? null;

        $product = is_string($slug) && $slug !== ''
            ? Product::query()->active()->with('category')->where('slug', $slug)->first()
            : null;

        if ($product === null) {
            return ['error' => 'Prodotto non trovato.'];
        }

        return [
            'product' => [
                ...$this->summary($product),
                'specifications' => array_map(
                    fn (array $row): string => "{$row['label']}: {$row['value']}",
                    $product->specifications,
                ),
            ],
        ];
    }

    /**
     * One section of the support page, or all of them when the section is
     * missing or not valid.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{sections: list<array{title: string, paragraphs: list<string>}>}
     */
    private function getShopInfo(array $arguments): array
    {
        $sections = $this->supportSections->execute();
        $requested = $arguments['section'] ?? null;

        if (is_string($requested) && in_array($requested, array_column($sections, 'id'), true)) {
            $sections = array_values(array_filter($sections, fn (array $section): bool => $section['id'] === $requested));
        }

        return [
            'sections' => array_map(
                fn (array $section): array => ['title' => $section['title'], 'paragraphs' => $section['paragraphs']],
                $sections,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function sectionIds(): array
    {
        return array_column($this->supportSections->execute(), 'id');
    }

    /**
     * @return array{name: string, slug: string, category: string, price: string, availability: string, short_description: string}
     */
    private function summary(Product $product): array
    {
        return [
            'name' => $product->name,
            'slug' => $product->slug,
            'category' => $product->category->name,
            'price' => Money::format($product->price),
            'availability' => $product->availability['label'],
            'short_description' => $product->short_description,
        ];
    }
}
