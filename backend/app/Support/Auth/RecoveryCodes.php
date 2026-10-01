<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\Hash;

/**
 * Recovery codes in the XXXX-XXXX format. The alphabet leaves out the
 * characters that are easy to confuse (0, O, 1, I), and the codes are
 * stored only as bcrypt hashes: they have low entropy, so a fast hash
 * would be easy to force after a database leak.
 */
final class RecoveryCodes
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @return list<string>
     */
    public static function generate(int $count): array
    {
        $codes = [];

        while (count($codes) < $count) {
            $codes[self::randomCode()] = true;
        }

        return array_keys($codes);
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public static function hash(#[\SensitiveParameter] array $codes): array
    {
        return array_map(fn (string $code): string => Hash::make($code), $codes);
    }

    /**
     * Upper case without spaces, as the codes are generated.
     */
    public static function normalize(#[\SensitiveParameter] string $code): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $code));
    }

    private static function randomCode(): string
    {
        $characters = '';

        for ($i = 0; $i < 8; $i++) {
            $characters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return substr($characters, 0, 4).'-'.substr($characters, 4);
    }
}
