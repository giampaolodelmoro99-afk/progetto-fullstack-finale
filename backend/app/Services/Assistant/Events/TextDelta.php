<?php

namespace App\Services\Assistant\Events;

/**
 * A fragment of the text answer.
 */
final readonly class TextDelta
{
    public function __construct(public string $delta) {}
}
