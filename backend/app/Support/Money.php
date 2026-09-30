<?php

namespace App\Support;

use NumberFormatter;

final class Money
{
    /**
     * Formats an amount in cents as an Italian price: 349,00 €, 1.249,00 €.
     */
    public static function format(int $cents): string
    {
        $formatter = new NumberFormatter('it_IT', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::GROUPING_USED, 1);

        $formatted = (string) $formatter->formatCurrency($cents / 100, 'EUR');

        return str_replace(["\u{00A0}", "\u{202F}"], ' ', $formatted);
    }
}
