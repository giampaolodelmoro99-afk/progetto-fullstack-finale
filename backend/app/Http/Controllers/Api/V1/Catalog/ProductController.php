<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Actions\Catalog\GetPriceRangeAction;
use App\Actions\Catalog\SearchProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\Product\IndexProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ProductController extends Controller
{
    public function index(
        IndexProductRequest $request,
        SearchProductsAction $searchProducts,
        GetPriceRangeAction $getPriceRange,
    ): AnonymousResourceCollection {
        $filters = $request->filters();

        $products = $searchProducts->execute($filters)
            ->with(['category', 'cover'])
            ->paginate($request->perPage())
            ->withQueryString();

        return ProductResource::collection($products)->additional([
            'meta' => ['price_range' => $getPriceRange->execute($filters['category'] ?? null)],
        ]);
    }

    public function show(Product $product): ProductDetailResource
    {
        Gate::authorize('viewInShop', $product);

        return ProductDetailResource::make($product->load(['category', 'images']));
    }
}
