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
use GuzzleHttp\Psr7\Exception\TimeoutException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Ollama /api/chat with streaming, through the Laravel HTTP client.
 *
 * The system instructions live only in the Modelfile: this class never sends
 * a system message. Shop data reaches the model only as tool results.
 */
final class OllamaAssistant implements AiAssistant
{
    /**
     * Markers of the data block used by the first version of the prompt.
     * The block no longer exists; the markers are still stripped, so no text
     * can pass itself off as genuine shop data.
     */
    private const DATA_MARKER = '/<\s*\/?\s*dati_negozio\s*>/iu';

    private const ROLES = ['user', 'assistant', 'tool'];

    public function isAvailable(): bool
    {
        try {
            $response = $this->client()
                ->timeout(config('assistant.connect_timeout'))
                ->get('/api/tags');
        } catch (ConnectionException) {
            return false;
        }

        if ($response->failed()) {
            return false;
        }

        $model = config('assistant.model');

        return collect($response->json('models', []))
            ->pluck('name')
            ->contains(fn (mixed $name): bool => $name === $model || $name === "{$model}:latest");
    }

    public function stream(array $messages, array $tools = [], ?CarbonInterface $deadline = null): iterable
    {
        $deadline ??= now()->addSeconds(config('assistant.timeout'));

        $response = $this->open($this->payload($messages, $tools), $deadline);

        return $this->events($response->toPsrResponse()->getBody(), $deadline);
    }

    /**
     * Body sent to /api/chat.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function payload(array $messages, array $tools): array
    {
        $payload = [
            'model' => config('assistant.model'),
            'messages' => $this->sanitize($messages),
            'stream' => true,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        if (config('assistant.think') !== null) {
            $payload['think'] = config('assistant.think');
        }

        return $payload;
    }

    /**
     * Keeps only the allowed roles and strips the data block markers from
     * every message, tool results included.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    private function sanitize(array $messages): array
    {
        $sanitized = [];

        foreach ($messages as $message) {
            if (! in_array($message['role'] ?? null, self::ROLES, true)) {
                continue;
            }

            $clean = [
                'role' => $message['role'],
                'content' => preg_replace(self::DATA_MARKER, '', (string) ($message['content'] ?? '')),
            ];

            if ($message['role'] === 'assistant' && ! empty($message['tool_calls'])) {
                $clean['tool_calls'] = array_map(fn (array $call): array => [
                    'function' => ['name' => $call['name'], 'arguments' => (object) $call['arguments']],
                ], $message['tool_calls']);
            }

            if ($message['role'] === 'tool') {
                $clean['tool_name'] = (string) ($message['tool_name'] ?? '');
            }

            $sanitized[] = $clean;
        }

        return $sanitized;
    }

    /**
     * Opens the streaming request. Runs before the answer starts, so a
     * connection failure becomes a 503 while the headers can still be sent.
     *
     * @param  array<string, mixed>  $payload
     */
    private function open(array $payload, CarbonInterface $deadline): Response
    {
        try {
            $response = $this->client()
                ->timeout(max(1, (int) ceil(now()->diffInSeconds($deadline))))
                ->withOptions(['stream' => true])
                ->post('/api/chat', $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Assistant service unreachable', ['error' => $exception->getMessage()]);

            throw new AssistantUnavailableException;
        }

        if ($response->failed()) {
            Log::warning('Assistant service rejected the request', ['status' => $response->status()]);

            throw new AssistantUnavailableException;
        }

        return $response;
    }

    /**
     * Reads the NDJSON body line by line. Kept separate from stream(): a
     * method containing yield runs nothing until it is iterated.
     *
     * Lines are read with Utils::readLine(), which returns as soon as a line
     * is complete: a read of a fixed size on the PHP http stream waits for
     * that many bytes, so fragments would arrive in batches.
     *
     * @return Generator<int, TextDelta|ToolCallRequested>
     */
    private function events(StreamInterface $body, CarbonInterface $deadline): Generator
    {
        try {
            while (! $body->eof()) {
                if (now()->greaterThan($deadline)) {
                    throw new AssistantTimeoutException;
                }

                $line = trim(Utils::readLine($body));

                if ($line === '') {
                    continue;
                }

                $done = yield from $this->parseLine($line);

                if ($done) {
                    return;
                }
            }
        } catch (AssistantTimeoutException|AssistantFailedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->isTimeout($exception) || now()->greaterThan($deadline)) {
                throw new AssistantTimeoutException;
            }

            throw new AssistantFailedException($exception->getMessage(), $exception);
        } finally {
            $body->close();
        }
    }

    /**
     * @return Generator<int, TextDelta|ToolCallRequested, mixed, bool> true when the answer is complete
     */
    private function parseLine(string $line): Generator
    {
        $chunk = json_decode($line, true);

        if (! is_array($chunk)) {
            throw new AssistantFailedException('Invalid line in the model response.');
        }

        if (isset($chunk['error'])) {
            throw new AssistantFailedException((string) $chunk['error']);
        }

        foreach ($chunk['message']['tool_calls'] ?? [] as $call) {
            $arguments = $call['function']['arguments'] ?? [];

            if (is_string($arguments)) {
                $arguments = json_decode($arguments, true);
            }

            yield new ToolCallRequested(
                (string) ($call['function']['name'] ?? ''),
                is_array($arguments) ? $arguments : [],
            );
        }

        $content = $chunk['message']['content'] ?? '';

        if (is_string($content) && $content !== '') {
            yield new TextDelta($content);
        }

        return (bool) ($chunk['done'] ?? false);
    }

    /**
     * Guzzle signals a stalled read with TimeoutException; the message check
     * is a fallback for errors raised by the PHP stream itself.
     */
    private function isTimeout(Throwable $exception): bool
    {
        return $exception instanceof TimeoutException
            || str_contains(strtolower($exception->getMessage()), 'timed out');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('assistant.base_url'))
            ->connectTimeout(config('assistant.connect_timeout'))
            ->acceptJson();
    }
}
