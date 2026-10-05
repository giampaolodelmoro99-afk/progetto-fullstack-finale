<?php

namespace Tests\Support;

use App\Contracts\ClientConnection;

/**
 * A client that closes the connection after the given number of checks;
 * without abortAfter() it never does.
 */
final class FakeClientConnection implements ClientConnection
{
    private ?int $abortAfter = null;

    private int $checks = 0;

    public function abortAfter(int $checks): self
    {
        $this->abortAfter = $checks;

        return $this;
    }

    public function aborted(): bool
    {
        $this->checks++;

        return $this->abortAfter !== null && $this->checks >= $this->abortAfter;
    }
}
