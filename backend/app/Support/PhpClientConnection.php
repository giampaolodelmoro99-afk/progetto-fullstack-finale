<?php

namespace App\Support;

use App\Contracts\ClientConnection;

/**
 * Reads the connection status of PHP. It reports a disconnection only after
 * a write has failed, so the caller checks it after flushing each line.
 */
final class PhpClientConnection implements ClientConnection
{
    public function aborted(): bool
    {
        return connection_aborted() === 1;
    }
}
