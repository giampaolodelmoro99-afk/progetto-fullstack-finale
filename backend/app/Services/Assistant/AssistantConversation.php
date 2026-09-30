<?php

namespace App\Services\Assistant;

use App\Contracts\AiAssistant;
use App\Exceptions\Assistant\AssistantFailedException;
use App\Exceptions\Assistant\AssistantTimeoutException;
use App\Exceptions\Assistant\AssistantUnavailableException;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use App\Services\Assistant\Events\ToolResult;
use Carbon\CarbonInterface;
use Generator;

/**
 * Runs the rounds of an answer: calls the model, runs the requested tools,
 * sends their results back and repeats until the model answers with text
 * only. Past max_tool_rounds the model is asked for the final answer
 * without tools. The timeout covers every round.
 *
 * Once the answer is streaming, every error is a timeout or a failure: a
 * model that becomes unreachable between two rounds is a failure, because
 * the 503 can no longer be sent.
 */
final class AssistantConversation
{
    public function __construct(
        private readonly AiAssistant $assistant,
        private readonly AssistantTools $tools,
    ) {}

    /**
     * Opens the first round before returning, so an unreachable model
     * surfaces here and not while iterating.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return iterable<TextDelta|ToolCallRequested|ToolResult>
     *
     * @throws AssistantUnavailableException
     */
    public function run(array $messages): iterable
    {
        $deadline = now()->addSeconds(config('assistant.timeout'));
        $tools = $this->tools->definitions();

        $first = $this->assistant->stream($messages, $tools, $deadline);

        return $this->rounds($messages, $first, $tools, $deadline);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  iterable<TextDelta|ToolCallRequested>  $events
     * @param  list<array<string, mixed>>  $tools
     * @return Generator<int, TextDelta|ToolCallRequested|ToolResult>
     */
    private function rounds(array $messages, iterable $events, array $tools, CarbonInterface $deadline): Generator
    {
        $round = 0;
        $toolsOffered = true;

        while (true) {
            $calls = [];
            $text = '';

            foreach ($events as $event) {
                if ($event instanceof ToolCallRequested) {
                    if ($toolsOffered) {
                        $calls[] = $event;
                        yield $event;
                    }

                    continue;
                }

                $text .= $event->delta;
                yield $event;
            }

            if ($calls === []) {
                return;
            }

            $round++;

            $messages[] = [
                'role' => 'assistant',
                'content' => $text,
                'tool_calls' => array_map(
                    fn (ToolCallRequested $call): array => ['name' => $call->name, 'arguments' => $call->arguments],
                    $calls,
                ),
            ];

            foreach ($calls as $call) {
                $result = $this->tools->call($call->name, $call->arguments);

                yield new ToolResult($call->name, $result);

                $messages[] = [
                    'role' => 'tool',
                    'tool_name' => $call->name,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ];
            }

            if (now()->greaterThan($deadline)) {
                throw new AssistantTimeoutException;
            }

            $toolsOffered = $round < config('assistant.max_tool_rounds');

            try {
                $events = $this->assistant->stream($messages, $toolsOffered ? $tools : [], $deadline);
            } catch (AssistantUnavailableException $exception) {
                throw new AssistantFailedException('Model unavailable between tool rounds.', $exception);
            }
        }
    }
}
