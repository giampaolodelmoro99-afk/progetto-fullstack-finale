<?php

namespace App\Exceptions\Admin;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * The account to remove is no longer an active admin when its row is
 * locked: removed by another admin:remove in the meantime. Only the
 * command throws and catches it, so it has no HTTP rendering.
 *
 * Foreseen refusal, not a fault: kept out of the error log.
 */
final class AdminNotFoundException extends RuntimeException implements ShouldntReport
{
    public const MESSAGE = 'Questo account non è più un amministratore attivo.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
