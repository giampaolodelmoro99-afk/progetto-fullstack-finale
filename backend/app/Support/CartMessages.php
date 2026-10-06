<?php

namespace App\Support;

/**
 * Validation messages of the cart, shared by the form requests and the
 * actions that check them again on the current stock.
 */
final class CartMessages
{
    public const UNAVAILABLE_PRODUCT = 'Questo prodotto non è disponibile.';

    public const ROW_NOT_PURCHASABLE = 'Questo prodotto non è più disponibile: rimuovilo dal carrello.';

    public static function maxQuantity(int $max): string
    {
        $pieces = $max === 1 ? '1 pezzo' : "{$max} pezzi";

        return "Puoi avere al massimo {$pieces} di questo prodotto nel carrello.";
    }
}
