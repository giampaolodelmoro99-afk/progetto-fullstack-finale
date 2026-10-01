<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects every address with the reserved top-level domain .invalid
 * (RFC 2606), in any letter case. Anonymized accounts use the predictable
 * deleted-{id}@anonymized.invalid: if someone could register it first, the
 * unique index on users.email would make that account deletion fail.
 * Used by the registration and, from Phase 3b, by the email change.
 */
final class NotReservedEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/\.invalid$/i', $value) === 1) {
            $fail('Inserisci un indirizzo email valido.');
        }
    }
}
