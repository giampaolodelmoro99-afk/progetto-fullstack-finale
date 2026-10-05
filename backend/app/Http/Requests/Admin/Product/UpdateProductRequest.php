<?php

namespace App\Http\Requests\Admin\Product;

use App\Models\Product;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

/**
 * The product form, plus the order and the alternative texts of the images:
 * when present, images lists every image of the product, each once, in the
 * order wanted. Missing, repeated or foreign ids are rejected.
 */
final class UpdateProductRequest extends SaveProductRequest
{
    public const IMAGES_MISMATCH_MESSAGE = 'Le immagini non corrispondono a quelle del prodotto: ricarica la pagina.';

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->product());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'images' => ['sometimes', 'list'],
            'images.*' => ['array:id,alt'],
            'images.*.id' => ['required', 'integer', 'distinct'],
            'images.*.alt' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'images' => 'immagini',
            'images.*' => 'immagine',
            'images.*.id' => 'immagine',
            'images.*.alt' => 'testo alternativo',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $images = $this->input('images');

                if (! is_array($images) || $validator->errors()->hasAny(['images', 'images.*'])) {
                    return;
                }

                $sent = collect($images)->pluck('id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
                $current = $this->product()->images()->pluck('id')->sort()->values()->all();

                if ($sent !== $current) {
                    $validator->errors()->add('images', self::IMAGES_MISMATCH_MESSAGE);
                }
            },
        ];
    }

    protected function uniqueSku(): Unique
    {
        return Rule::unique('products', 'sku')->ignore($this->product());
    }

    private function product(): Product
    {
        return $this->route('product');
    }
}
