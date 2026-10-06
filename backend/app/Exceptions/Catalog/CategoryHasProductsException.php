<?php

namespace App\Exceptions\Catalog;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deletion of a category linked to products, soft deleted ones included.
 * The two messages are the tooltips of the admin mockup: one for products
 * still in the catalog, one for a category linked only to deleted products.
 *
 * Foreseen answer to the client, not a fault: kept out of the error log.
 */
final class CategoryHasProductsException extends RuntimeException implements ShouldntReport
{
    public const CODE = 'category_has_products';

    public const HAS_PRODUCTS_MESSAGE = 'Contiene prodotti: spostali prima di eliminarla.';

    public const HAS_DELETED_PRODUCTS_MESSAGE = 'È collegata a prodotti eliminati: non si può eliminare.';

    public static function withProducts(): self
    {
        return new self(self::HAS_PRODUCTS_MESSAGE);
    }

    public static function withDeletedProducts(): self
    {
        return new self(self::HAS_DELETED_PRODUCTS_MESSAGE);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
        ], Response::HTTP_CONFLICT);
    }
}
