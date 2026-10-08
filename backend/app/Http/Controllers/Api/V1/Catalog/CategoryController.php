<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController extends Controller
{
    /**
     * The categories with at least one active product, by id: new ones come
     * last. The admin list keeps every category.
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->withActiveProducts()->orderBy('id')->get());
    }

    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }
}
