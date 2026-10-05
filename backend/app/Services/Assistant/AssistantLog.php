<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Log;

/**
 * The only log line of an answer: outcome, duration, number of tool calls
 * and, for a failure, the technical reason. Never the conversation.
 */
final class AssistantLog
{
    public const COMPLETED = 'completed';

    public const ABORTED = 'aborted';

    public const TIMEOUT = 'timeout';

    public const FAILED = 'failed';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @param  int  $startedAt  hrtime() in nanoseconds
     */
    public static function answer(string $outcome, int $startedAt, int $toolCalls = 0, ?string $reason = null): void
    {
        $context = [
            'outcome' => $outcome,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1e6),
            'tool_calls' => $toolCalls,
        ];

        if ($reason !== null && $reason !== '') {
            $context['reason'] = $reason;
        }

        Log::info('Assistant answer', $context);
    }
}
