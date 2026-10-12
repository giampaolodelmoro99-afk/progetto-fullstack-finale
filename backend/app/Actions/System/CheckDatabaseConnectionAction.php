<?php

namespace App\Actions\System;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;

/**
 * Whether the default database connection answers, for the health check:
 * the healthcheck of compose then sees a stopped or unreachable MySQL.
 */
final class CheckDatabaseConnectionAction
{
    public function execute(): bool
    {
        try {
            DB::connection()->select('select 1');

            return true;
        } catch (QueryException|PDOException) {
            return false;
        }
    }
}
