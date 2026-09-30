<?php

namespace App\Services\Assistant;

use App\Contracts\AiAssistant;
use App\Exceptions\Assistant\AssistantFailedException;
use App\Exceptions\Assistant\AssistantTimeoutException;
use App\Exceptions\Assistant\AssistantUnavailableException;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use Carbon\CarbonInterface;
use Generator;

/**
 * Programmable assistant for the tests and for ASSISTANT_DRIVER=fake.
 *
 * Every call to stream() consumes the next programmed turn. The received
 * messages and tools are recorded, so tests can check what would
 * have been sent to the model, tool results included.
 */
final class FakeAiAssistant implements AiAssistant
{
    public const DEFAULT_ANSWER = 'Risposta di prova dell\'assistente.';

    /** @var list<array{events: list<TextDelta|ToolCallRequested>, failure: ?string, after: int}> */
    private array $turns = [];

    private bool $available = true;

    /** @var list<array{messages: array<int, array<string, mixed>>, tools: array<int, array<string, mixed>>}> */
    private array $calls = [];

    /**
     * Programs the next answer. Strings are text fragments.
     */
    public function respondWith(TextDelta|ToolCallRequested|string ...$events): self
    {
        $this->turns[] = [
            'events' => array_map(
                fn (TextDelta|ToolCallRequested|string $event): TextDelta|ToolCallRequested => is_string($event) ? new TextDelta($event) : $event,
                array_values($events),
            ),
            'failure' => null,
            'after' => 0,
        ];

        return $this;
    }

    /**
     * The last programmed answer fails with an error after the given number of events.
     */
    public function failAfter(int $events): self
    {
        return $this->interruptLastTurn('error', $events);
    }

    /**
     * The last programmed answer times out after the given number of events.
     */
    public function timeoutAfter(int $events): self
    {
        return $this->interruptLastTurn('timeout', $events);
    }

    public function unavailable(): self
    {
        $this->available = false;

        return $this;
    }

    /**
     * @return list<array{messages: array<int, array<string, mixed>>, tools: array<int, array<string, mixed>>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function stream(array $messages, array $tools = [], ?CarbonInterface $deadline = null): iterable
    {
        if (! $this->available) {
            throw new AssistantUnavailableException;
        }

        $this->calls[] = ['messages' => $messages, 'tools' => $tools];

        $turn = array_shift($this->turns) ?? [
            'events' => [new TextDelta(self::DEFAULT_ANSWER)],
            'failure' => null,
            'after' => 0,
        ];

        return $this->play($turn);
    }

    /**
     * @param  array{events: list<TextDelta|ToolCallRequested>, failure: ?string, after: int}  $turn
     * @return Generator<int, TextDelta|ToolCallRequested>
     */
    private function play(array $turn): Generator
    {
        foreach ($turn['events'] as $index => $event) {
            if ($turn['failure'] !== null && $index === $turn['after']) {
                break;
            }

            yield $event;
        }

        match ($turn['failure']) {
            'error' => throw new AssistantFailedException('Fake failure.'),
            'timeout' => throw new AssistantTimeoutException,
            default => null,
        };
    }

    private function interruptLastTurn(string $failure, int $events): self
    {
        if ($this->turns === []) {
            $this->respondWith();
        }

        $last = array_key_last($this->turns);
        $this->turns[$last]['failure'] = $failure;
        $this->turns[$last]['after'] = $events;

        return $this;
    }
}
