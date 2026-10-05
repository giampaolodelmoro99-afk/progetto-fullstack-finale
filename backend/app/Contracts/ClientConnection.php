<?php

namespace App\Contracts;

/**
 * The connection of the client of a streamed response, behind a contract so
 * that the tests can simulate a disconnection.
 */
interface ClientConnection
{
    /**
     * Whether the client has closed the connection.
     */
    public function aborted(): bool;
}
