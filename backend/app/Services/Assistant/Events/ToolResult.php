<?php

namespace App\Services\Assistant\Events;

/**
 * The result of a tool call, as returned to the model.
 */
final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        public string $name,
        public array $result,
    ) {}
}
