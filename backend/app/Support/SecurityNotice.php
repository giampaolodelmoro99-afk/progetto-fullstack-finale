<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Shared wording of the security notices: when and from which device the
 * operation was done, captured at the request because a queued
 * notification has none, and the invitation to change the password.
 */
final class SecurityNotice
{
    public const NOT_YOU = 'Se non hai fatto tu questa operazione, cambia subito la password e controlla la pagina Sicurezza del tuo account.';

    public static function occurredOn(CarbonInterface $occurredAt, string $device): string
    {
        $date = $occurredAt->locale('it')->translatedFormat('j F Y');

        return "il {$date} alle {$occurredAt->format('H:i')} da {$device}";
    }
}
