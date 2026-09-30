<?php

namespace App\Console\Commands;

use App\Exceptions\Assistant\AssistantBusyException;
use App\Exceptions\Assistant\AssistantFailedException;
use App\Exceptions\Assistant\AssistantTimeoutException;
use App\Exceptions\Assistant\AssistantUnavailableException;
use App\Services\Assistant\AssistantConversation;
use App\Services\Assistant\AssistantSlots;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use App\Services\Assistant\Events\ToolResult;
use Illuminate\Console\Command;
use Throwable;

final class AssistantAskCommand extends Command
{
    protected $signature = 'assistant:ask
        {question* : The question for the assistant}
        {--timing : Print the time to the first word and the total time}';

    protected $description = 'Ask the shop assistant a question and print the streamed answer and the tool calls';

    public function handle(AssistantSlots $slots, AssistantConversation $conversation): int
    {
        $question = implode(' ', $this->argument('question'));

        $slot = $slots->acquire();

        if ($slot === null) {
            $this->components->error((new AssistantBusyException)->getMessage());

            return self::FAILURE;
        }

        $start = hrtime(true);
        $firstWord = null;

        try {
            $events = $conversation->run([['role' => 'user', 'content' => $question]]);

            foreach ($events as $event) {
                match (true) {
                    $event instanceof TextDelta => $this->output->write($event->delta),
                    $event instanceof ToolCallRequested => $this->line(sprintf(
                        '<fg=cyan>→ %s %s</>',
                        $event->name,
                        json_encode($event->arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    )),
                    $event instanceof ToolResult => $this->line(sprintf('<fg=cyan>← %s</>', $this->describe($event))),
                };

                if ($firstWord === null && $event instanceof TextDelta && trim($event->delta) !== '') {
                    $firstWord = $this->elapsed($start);
                }
            }

            $this->newLine();
        } catch (AssistantUnavailableException|AssistantTimeoutException|AssistantFailedException $exception) {
            $this->newLine();
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->newLine();
            $this->components->error((new AssistantFailedException)->getMessage());
            report($exception);

            return self::FAILURE;
        } finally {
            $slot->release();
        }

        if ($this->option('timing')) {
            $this->components->twoColumnDetail('Prima parola', $firstWord === null ? '—' : $this->seconds($firstWord));
            $this->components->twoColumnDetail('Totale', $this->seconds($this->elapsed($start)));
        }

        return self::SUCCESS;
    }

    private function describe(ToolResult $result): string
    {
        return match (true) {
            isset($result->result['products']) => trans_choice('{0} nessun prodotto|{1} 1 prodotto|[2,*] :count prodotti', count($result->result['products']))
                .rtrim(': '.implode(', ', array_column($result->result['products'], 'slug')), ': '),
            isset($result->result['product']) => 'prodotto '.$result->result['product']['slug'],
            isset($result->result['sections']) => 'regole: '.implode(', ', array_column($result->result['sections'], 'title')),
            default => (string) ($result->result['error'] ?? ''),
        };
    }

    private function elapsed(int $start): float
    {
        return (hrtime(true) - $start) / 1e9;
    }

    private function seconds(float $seconds): string
    {
        return number_format($seconds, 1, ',', '.').' s';
    }
}
