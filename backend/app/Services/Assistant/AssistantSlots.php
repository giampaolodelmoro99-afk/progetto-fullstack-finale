<?php

namespace App\Services\Assistant;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Concurrency slots for the generations, as atomic cache locks. The lock
 * lives for the whole timeout plus a margin, so a slot held by a dead worker
 * frees itself. The caller owns the returned lock and releases it in a
 * finally block.
 */
final class AssistantSlots
{
    /**
     * The first free slot, or null when every slot is taken.
     */
    public function acquire(): ?Lock
    {
        $seconds = config('assistant.timeout') + config('assistant.lock_margin');

        for ($slot = 1; $slot <= config('assistant.max_concurrent'); $slot++) {
            $lock = Cache::lock("assistant:slot:{$slot}", $seconds);

            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }
}
