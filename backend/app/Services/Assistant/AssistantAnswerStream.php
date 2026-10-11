<?php

namespace App\Services\Assistant;

use App\Contracts\ClientConnection;
use App\Exceptions\Assistant\AssistantFailedException;
use App\Exceptions\Assistant\AssistantTimeoutException;
use App\Http\Resources\ProductResource;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Streams an answer to the client as NDJSON, one event per line: text
 * fragments, then the cited products and done, or an error that ends the
 * answer. Tool calls and their results stay on the server.
 *
 * The callback owns the concurrency slot and finishes the answer on every
 * exit: end of the answer, error, timeout or client disconnection.
 */
final class AssistantAnswerStream
{
    /**
     * The error event arrives after the answer has started, so the client
     * sees one message for every cause; the log keeps timeout and failure
     * apart.
     */
    public const INTERRUPTED_MESSAGE = 'La risposta si è interrotta prima della fine.';

    public function __construct(
        private readonly CitedProducts $citedProducts,
        private readonly ClientConnection $connection,
    ) {}

    public function toResponse(AssistantAnswer $answer): StreamedResponse
    {
        // The application terminates after the body has been sent. If the
        // callback never ran, for instance because a middleware failed after
        // the controller, the slot is released here instead of at its expiry.
        app()->terminating(fn () => $answer->releaseSlot());

        return response()->stream(fn () => $this->send($answer), 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function send(AssistantAnswer $answer): void
    {
        // Keep running after a disconnection, so the finally block runs.
        ignore_user_abort(true);

        $text = '';
        $toolCalls = 0;
        $outcome = AssistantLog::COMPLETED;
        $reason = null;

        try {
            foreach ($answer->events() as $event) {
                if ($event instanceof ToolCallRequested) {
                    $toolCalls++;
                } elseif ($event instanceof TextDelta) {
                    $text .= $event->delta;
                    $this->write(['type' => 'text', 'delta' => $event->delta]);
                }

                if ($this->connection->aborted()) {
                    $outcome = AssistantLog::ABORTED;

                    // Leaving the loop drops its reference to the generator, so
                    // finish() can destroy it.
                    break;
                }
            }

            if ($outcome === AssistantLog::ABORTED) {
                return;
            }

            $products = $this->citedProducts->fromText($text);

            $this->write(['type' => 'products', 'data' => ProductResource::collection($products)->resolve()]);
            $this->write(['type' => 'done']);
        } catch (AssistantTimeoutException) {
            $outcome = AssistantLog::TIMEOUT;
            $this->write(['type' => 'error', 'message' => self::INTERRUPTED_MESSAGE]);
        } catch (AssistantFailedException $exception) {
            $outcome = AssistantLog::FAILED;
            $reason = $exception->reason;
            $this->write(['type' => 'error', 'message' => self::INTERRUPTED_MESSAGE]);
        } catch (Throwable $exception) {
            $outcome = AssistantLog::FAILED;
            $reason = $exception::class;
            report($exception);
            $this->write(['type' => 'error', 'message' => self::INTERRUPTED_MESSAGE]);
        } finally {
            $answer->finish();
            AssistantLog::answer($outcome, $answer->startedAt, $toolCalls, $reason);
        }
    }

    /**
     * Writes one line and pushes it to the client at once.
     *
     * @param  array<string, mixed>  $event
     */
    private function write(array $event): void
    {
        echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
