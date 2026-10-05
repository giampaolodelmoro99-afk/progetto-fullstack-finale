<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\DeleteCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\StoreCategoryRequest;
use App\Http\Requests\Admin\Category\UpdateCategoryRequest;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class CategoryController extends Controller
{
    /**
     * Every category by id, with the counts loaded in the same query.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);

        return CategoryResource::collection(Category::query()->withProductCounts()->orderBy('id')->get());
    }

    /**
     * CategoryObserver derives the slug from the name.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return CategoryResource::make($category->loadProductCounts())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The slug is not among the validated fields, so it never changes.
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return CategoryResource::make($category->loadProductCounts());
    }

    public function destroy(Category $category, DeleteCategoryAction $deleteCategory): Response
    {
        Gate::authorize('delete', $category);

        $deleteCategory->execute($category);

        return response()->noContent();
    }
}
