<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Actions\Catalog\GetRelatedProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class RelatedProductController extends Controller
{
    public function __invoke(Product $product, GetRelatedProductsAction $getRelatedProducts): AnonymousResourceCollection
    {
        Gate::authorize('viewInShop', $product);

        return ProductResource::collection($getRelatedProducts->execute($product));
    }
}
