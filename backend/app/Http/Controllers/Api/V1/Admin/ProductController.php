<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\CreateProductAction;
use App\Actions\Admin\SearchProductsAction;
use App\Actions\Admin\UpdateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\IndexProductRequest;
use App\Http\Requests\Admin\Product\StoreProductRequest;
use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ProductController extends Controller
{
    public function index(IndexProductRequest $request, SearchProductsAction $searchProducts): AnonymousResourceCollection
    {
        $products = $searchProducts->execute($request->filters())
            ->with(['category', 'cover'])
            ->paginate($request->perPage())
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, CreateProductAction $createProduct): JsonResponse
    {
        $product = $createProduct->execute($request->validated());

        return ProductResource::make($product->load(['category', 'images']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Deactivated products too: only the deleted ones answer 404.
     */
    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return ProductResource::make($product->load(['category', 'images']));
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProductAction $updateProduct): ProductResource
    {
        $product = $updateProduct->execute($product, $request->validated());

        return ProductResource::make($product->load(['category', 'images']));
    }

    /**
     * Soft delete: the product leaves catalog and admin list, while orders
     * keep their snapshot and the images stay with the product.
     */
    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }
}
