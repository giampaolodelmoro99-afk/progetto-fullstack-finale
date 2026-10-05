<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController extends Controller
{
    /**
     * Every category, empty ones included, by id: new ones come last.
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->orderBy('id')->get());
    }

    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }
}
