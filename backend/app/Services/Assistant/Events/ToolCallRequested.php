<?php

namespace App\Services\Assistant\Events;

/**
 * A tool call proposed by the model. The arguments are untrusted input.
 */
final readonly class ToolCallRequested
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
    ) {}
}
