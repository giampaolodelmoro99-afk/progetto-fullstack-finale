<?php

namespace App\Exceptions\Checkout;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cart cannot be bought as it is: a row above the stock, or a product
 * out of stock, deactivated or deleted (available 0). The list names every
 * problematic product, so the checkout page can explain what changed.
 */
final class InsufficientStockException extends RuntimeException
{
    public const CODE = 'insufficient_stock';

    public const MESSAGE = 'La disponibilità di alcuni prodotti è cambiata: aggiorna il carrello per completare l\'ordine.';

    /**
     * @param  list<array{product: string, requested: int, available: int}>  $products
     */
    public function __construct(public readonly array $products)
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => self::MESSAGE,
            'code' => self::CODE,
            'products' => $this->products,
        ], Response::HTTP_CONFLICT);
    }
}
