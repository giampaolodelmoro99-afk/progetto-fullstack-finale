<?php

namespace App\Http\Requests\Cart;

use App\Support\CartMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A product of the catalog and a quantity. Only active, not deleted
 * products are accepted. The maximum is checked only by AddCartItemAction,
 * on the current stock and the quantity already in the cart: a fixed limit
 * here would name a maximum that is not the one of the product.
 */
final class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => CartMessages::UNAVAILABLE_PRODUCT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'prodotto',
            'quantity' => 'quantità',
        ];
    }
}
