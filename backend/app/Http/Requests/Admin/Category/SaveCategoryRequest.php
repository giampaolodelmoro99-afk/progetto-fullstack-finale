<?php

namespace App\Http\Requests\Admin\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Name and description of a category, shared by creation and update. The
 * slug is not among the rules: CategoryObserver derives it from the name on
 * creation and it never changes, so a slug sent by the client is ignored.
 * The name must be unique and so must the slug it would produce, with the
 * error on the name; the update ignores the category itself.
 */
abstract class SaveCategoryRequest extends FormRequest
{
    public const DUPLICATE_NAME_MESSAGE = 'Esiste già una categoria con questo nome';

    abstract protected function category(): ?Category;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('categories', 'name')->ignore($this->category())],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => self::DUPLICATE_NAME_MESSAGE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'description' => 'descrizione',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('name')) {
                    return;
                }

                $slugTaken = Category::query()
                    ->where('slug', Category::slugFrom($this->input('name')))
                    ->when($this->category(), fn ($query, Category $category) => $query->whereKeyNot($category->getKey()))
                    ->exists();

                if ($slugTaken) {
                    $validator->errors()->add('name', self::DUPLICATE_NAME_MESSAGE);
                }
            },
        ];
    }
}
