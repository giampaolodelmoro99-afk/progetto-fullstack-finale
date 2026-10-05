<?php

namespace App\Http\Requests\Admin\Category;

use App\Models\Category;

final class StoreCategoryRequest extends SaveCategoryRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    protected function category(): ?Category
    {
        return null;
    }
}
