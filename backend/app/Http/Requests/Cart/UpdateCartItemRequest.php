<?php

namespace App\Http\Requests\Cart;

use App\Models\CartItem;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * New quantity of a row of the own cart (a row of somebody else answers
 * 404). The maximum of the product is checked only by UpdateCartItemAction,
 * on the current stock, after the check that the row can still be bought.
 */
final class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): Response
    {
        $cartItem = $this->route('cartItem');

        return $cartItem instanceof CartItem ? Gate::inspect('update', $cartItem) : Response::denyAsNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity' => 'quantità',
        ];
    }
}
