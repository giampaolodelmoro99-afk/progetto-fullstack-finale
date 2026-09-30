<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Str;

final class CategoryObserver
{
    /**
     * The slug is derived from the name only on creation and never changes afterwards.
     * Collisions are rejected earlier by the Form Request, with an error on the name.
     */
    public function creating(Category $category): void
    {
        $category->slug = Str::slug($category->name) ?: 'categoria';
    }
}
