<?php

namespace App\Exceptions\Admin;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Removal of the last active admin from the admin:remove command: the shop
 * would be left without anyone who can manage it. Only the command throws
 * and catches it, so it has no HTTP rendering.
 *
 * Foreseen refusal, not a fault: kept out of the error log.
 */
final class LastAdminException extends RuntimeException implements ShouldntReport
{
    public const MESSAGE = 'Non puoi rimuovere l\'ultimo amministratore attivo.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
