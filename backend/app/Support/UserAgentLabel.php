<?php

namespace App\Support;

/**
 * Turns a raw user agent into «Browser su sistema», e.g. "Chrome su macOS".
 * Edge and Opera also contain "Chrome", and Chrome also contains "Safari",
 * so the most specific tokens are checked first; the same holds for iOS and
 * iPadOS ("like Mac OS X") and Android ("Linux").
 */
final class UserAgentLabel
{
    public const UNKNOWN = 'Dispositivo sconosciuto';

    /**
     * @var array<string, string>
     */
    private const BROWSERS = [
        'Edg' => 'Edge',
        'OPR/' => 'Opera',
        'Opera' => 'Opera',
        'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox',
        'Chrome/' => 'Chrome',
        'CriOS/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    /**
     * @var array<string, string>
     */
    private const SYSTEMS = [
        'iPad' => 'iPadOS',
        'iPhone' => 'iOS',
        'iPod' => 'iOS',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'Macintosh' => 'macOS',
        'Mac OS X' => 'macOS',
        'Linux' => 'Linux',
    ];

    public static function for(?string $userAgent): string
    {
        $browser = self::match((string) $userAgent, self::BROWSERS);
        $system = self::match((string) $userAgent, self::SYSTEMS);

        if ($browser === null || $system === null) {
            return self::UNKNOWN;
        }

        return "{$browser} su {$system}";
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private static function match(string $userAgent, array $tokens): ?string
    {
        foreach ($tokens as $token => $label) {
            if (str_contains($userAgent, $token)) {
                return $label;
            }
        }

        return null;
    }
}
