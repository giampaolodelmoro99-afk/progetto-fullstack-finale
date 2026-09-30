<?php

namespace App\Exceptions\Assistant;

use RuntimeException;

/**
 * The answer went past the configured timeout while streaming.
 */
final class AssistantTimeoutException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("L'assistente non ha risposto in tempo. Riprova tra poco.");
    }
}
