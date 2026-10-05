<?php

namespace App\Services\Assistant;

use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use App\Services\Assistant\Events\ToolResult;
use Illuminate\Contracts\Cache\Lock;

/**
 * An answer whose first model round is already open, with the concurrency
 * slot that it holds. Whoever streams the events owns the slot and calls
 * finish() when the answer ends.
 */
final class AssistantAnswer
{
    /** @var iterable<TextDelta|ToolCallRequested|ToolResult>|null */
    private ?iterable $events;

    private bool $slotReleased = false;

    /**
     * @param  iterable<TextDelta|ToolCallRequested|ToolResult>  $events
     * @param  int  $startedAt  hrtime() in nanoseconds
     */
    public function __construct(
        private readonly Lock $slot,
        iterable $events,
        public readonly int $startedAt,
    ) {
        $this->events = $events;
    }

    /**
     * @return iterable<TextDelta|ToolCallRequested|ToolResult>
     */
    public function events(): iterable
    {
        return $this->events ?? [];
    }

    /**
     * Stops the generation and releases the slot. Dropping the last
     * reference to the generators destroys them at once: their finally
     * blocks close the response body, so the connection to the model is
     * closed and the generation stops. Safe to call more than once.
     */
    public function finish(): void
    {
        $this->events = null;

        $this->releaseSlot();
    }

    /**
     * Releases the slot only, once.
     */
    public function releaseSlot(): void
    {
        if ($this->slotReleased) {
            return;
        }

        $this->slotReleased = true;
        $this->slot->release();
    }
}
