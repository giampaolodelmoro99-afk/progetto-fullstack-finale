<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

/**
 * From 1 to 10 images per request, JPG, PNG or WebP, at most 5 MB and
 * 8000 pixels per side each: 10 files of 5 MB stay within post_max_size
 * (64M), and the side limit keeps the decoding within memory_limit.
 */
final class StoreProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=8000,max_height=8000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'images' => 'immagini',
            'images.*' => 'immagine',
        ];
    }
}
