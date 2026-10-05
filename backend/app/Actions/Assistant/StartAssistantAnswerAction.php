<?php

namespace App\Actions\Assistant;

use App\Exceptions\Assistant\AssistantBusyException;
use App\Exceptions\Assistant\AssistantUnavailableException;
use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\AssistantConversation;
use App\Services\Assistant\AssistantLog;
use App\Services\Assistant\AssistantSlots;
use Throwable;

/**
 * Takes a concurrency slot and opens the first model round before the
 * response starts, so a busy or unreachable assistant still becomes a 503.
 * The slot passes to the returned answer; on any failure here it is
 * released at once.
 *
 * The conversation holds only the validated messages of the client: no
 * data of the user reaches the model.
 */
final class StartAssistantAnswerAction
{
    public function __construct(
        private readonly AssistantSlots $slots,
        private readonly AssistantConversation $conversation,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     *
     * @throws AssistantBusyException
     * @throws AssistantUnavailableException
     */
    public function execute(array $messages): AssistantAnswer
    {
        $slot = $this->slots->acquire();

        if ($slot === null) {
            throw new AssistantBusyException;
        }

        $startedAt = hrtime(true);

        try {
            $events = $this->conversation->run($messages);
        } catch (Throwable $exception) {
            $slot->release();

            AssistantLog::answer(
                $exception instanceof AssistantUnavailableException ? AssistantLog::UNAVAILABLE : AssistantLog::FAILED,
                $startedAt,
                reason: $exception instanceof AssistantUnavailableException ? null : $exception::class,
            );

            throw $exception;
        }

        return new AssistantAnswer($slot, $events, $startedAt);
    }
}
