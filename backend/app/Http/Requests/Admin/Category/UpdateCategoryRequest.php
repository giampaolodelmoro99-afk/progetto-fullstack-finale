<?php

namespace App\Http\Requests\Admin\Category;

use App\Models\Category;

final class UpdateCategoryRequest extends SaveCategoryRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->category());
    }

    protected function category(): Category
    {
        return $this->route('category');
    }
}
