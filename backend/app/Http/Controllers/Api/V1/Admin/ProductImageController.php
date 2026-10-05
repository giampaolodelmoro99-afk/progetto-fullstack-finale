<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\AddProductImagesAction;
use App\Actions\Admin\DeleteProductImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\StoreProductImagesRequest;
use App\Http\Resources\Admin\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ProductImageController extends Controller
{
    /**
     * Answers with every image of the product, in order.
     */
    public function store(StoreProductImagesRequest $request, Product $product, AddProductImagesAction $addImages): JsonResponse
    {
        $product = $addImages->execute($product, $request->file('images'));

        return ProductImageResource::collection($product->images()->get())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The binding is scoped: an image of another product answers 404.
     */
    public function destroy(Product $product, ProductImage $image, DeleteProductImageAction $deleteImage): Response
    {
        Gate::authorize('update', $product);

        $deleteImage->execute($product, $image);

        return response()->noContent();
    }
}
