<?php

namespace App\Exceptions\Assistant;

use RuntimeException;
use Throwable;

/**
 * Any other error while streaming. The technical reason is kept for the logs
 * and never contains the conversation.
 */
final class AssistantFailedException extends RuntimeException
{
    public function __construct(public readonly string $reason = '', ?Throwable $previous = null)
    {
        parent::__construct("Si è verificato un problema con l'assistente. Riprova tra poco.", 0, $previous);
    }
}
