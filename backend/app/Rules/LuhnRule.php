<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Luhn checksum of a card number made of digits only (spaces are removed
 * before the validation). The length is checked by its own rule.
 */
final class LuhnRule implements ValidationRule
{
    public function validate(string $attribute, #[\SensitiveParameter] mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('Il numero della carta non è valido.');
        }
    }

    public static function passes(#[\SensitiveParameter] string $number): bool
    {
        if (preg_match('/^\d+$/', $number) !== 1) {
            return false;
        }

        $sum = 0;
        $double = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($double) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
