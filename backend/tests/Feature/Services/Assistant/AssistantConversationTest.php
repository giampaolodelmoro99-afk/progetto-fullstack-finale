<?php

use App\Exceptions\Assistant\AssistantFailedException;
use App\Exceptions\Assistant\AssistantTimeoutException;
use App\Models\Category;
use App\Models\Product;
use App\Services\Assistant\AssistantConversation;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use App\Services\Assistant\Events\ToolResult;
use App\Services\Assistant\FakeAiAssistant;
use Illuminate\Support\Facades\Http;

function runAssistantConversation(string $question): array
{
    $received = [];

    foreach (app(AssistantConversation::class)->run([['role' => 'user', 'content' => $question]]) as $event) {
        $received[] = $event;
    }

    return $received;
}

beforeEach(function () {
    $this->fake = app(FakeAiAssistant::class);
});

it('runs the tool and returns only active products to the model', function () {
    $audio = Category::factory()->create(['name' => 'Audio']);
    Product::factory()->for($audio)->create(['name' => 'Cuffie Aria', 'price' => 12900]);
    Product::factory()->for($audio)->inactive()->create(['name' => 'Cuffie Nascoste', 'price' => 9900]);

    $this->fake
        ->respondWith(new ToolCallRequested('search_products', ['category' => 'audio', 'max_price' => 150]))
        ->respondWith('Ti consiglio ', 'le Cuffie Aria [[cuffie-aria]].');

    $events = runAssistantConversation('Cuffie sotto i 150 euro');

    expect($events)->toHaveCount(4)
        ->and($events[0])->toEqual(new ToolCallRequested('search_products', ['category' => 'audio', 'max_price' => 150]))
        ->and($events[1])->toBeInstanceOf(ToolResult::class)
        ->and($events[2])->toEqual(new TextDelta('Ti consiglio '))
        ->and($events[3])->toEqual(new TextDelta('le Cuffie Aria [[cuffie-aria]].'));

    $second = $this->fake->calls()[1]['messages'];

    expect(array_column($second, 'role'))->toBe(['user', 'assistant', 'tool'])
        ->and($second[1]['tool_calls'])->toBe([['name' => 'search_products', 'arguments' => ['category' => 'audio', 'max_price' => 150]]])
        ->and($second[2]['tool_name'])->toBe('search_products')
        ->and(array_column(json_decode($second[2]['content'], true)['products'], 'slug'))->toBe(['cuffie-aria']);
});

it('offers the tools with the category slugs in every round', function () {
    Category::factory()->create(['name' => 'Audio']);

    $this->fake
        ->respondWith(new ToolCallRequested('get_shop_info', ['section' => 'resi']))
        ->respondWith('Entro 30 giorni.');

    runAssistantConversation('Come funzionano i resi?');

    $calls = $this->fake->calls();

    expect($calls)->toHaveCount(2);

    foreach ($calls as $call) {
        $tools = collect($call['tools'])->keyBy('function.name');

        expect($tools->keys()->all())->toBe(['search_products', 'get_product', 'get_shop_info'])
            ->and($tools['search_products']['function']['parameters']['properties']['category']['enum'])->toBe(['audio']);
    }

    expect(json_decode($calls[1]['messages'][2]['content'], true)['sections'][0]['title'])->toBe('Resi e rimborsi');
});

it('asks for the final answer without tools after the maximum number of rounds', function () {
    config(['assistant.max_tool_rounds' => 2]);

    $this->fake
        ->respondWith(new ToolCallRequested('search_products', ['search' => 'cuffie']))
        ->respondWith(new ToolCallRequested('search_products', ['search' => 'auricolari']))
        ->respondWith(new ToolCallRequested('search_products', ['search' => 'diffusori']), 'Non ho trovato altro.');

    $events = runAssistantConversation('Cerca tutto');

    $calls = $this->fake->calls();

    expect($calls)->toHaveCount(3)
        ->and($calls[0]['tools'])->toHaveCount(3)
        ->and($calls[1]['tools'])->toHaveCount(3)
        ->and($calls[2]['tools'])->toBe([])
        ->and(collect($events)->whereInstanceOf(ToolCallRequested::class))->toHaveCount(2)
        ->and(end($events))->toEqual(new TextDelta('Non ho trovato altro.'));
});

it('stops the answer when a round times out', function () {
    $this->fake
        ->respondWith(new ToolCallRequested('search_products', []))
        ->respondWith('Ti consiglio ')->timeoutAfter(1);

    expect(fn () => runAssistantConversation('Cuffie?'))->toThrow(AssistantTimeoutException::class);
});

it('applies the timeout to all the rounds together', function () {
    $this->freezeTime();
    config(['assistant.driver' => 'ollama', 'assistant.timeout' => 60]);
    Http::fake(['ollama:11434/api/chat' => Http::sequence()
        ->push(json_encode(['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'search_products', 'arguments' => []]]]], 'done' => true])."\n")
        ->push(json_encode(['message' => ['role' => 'assistant', 'content' => 'Ecco.'], 'done' => true])."\n"),
    ]);

    $received = [];

    expect(function () use (&$received) {
        foreach (app(AssistantConversation::class)->run([['role' => 'user', 'content' => 'Cuffie?']]) as $event) {
            $received[] = $event;
            $this->travel(40)->seconds();
        }
    })->toThrow(AssistantTimeoutException::class)
        ->and($received[0])->toBeInstanceOf(ToolCallRequested::class)
        ->and(collect($received)->whereInstanceOf(TextDelta::class))->toBeEmpty();
});

it('does not open another round once the deadline has passed', function () {
    $this->freezeTime();
    config(['assistant.timeout' => 60]);

    $this->fake
        ->respondWith(new ToolCallRequested('search_products', []))
        ->respondWith('Ecco.');

    expect(function () {
        foreach (app(AssistantConversation::class)->run([['role' => 'user', 'content' => 'Cuffie?']]) as $event) {
            $this->travel(61)->seconds();
        }
    })->toThrow(AssistantTimeoutException::class)
        ->and($this->fake->calls())->toHaveCount(1);
});

it('turns a model unreachable between two rounds into a failure', function () {
    config(['assistant.driver' => 'ollama']);
    Http::fake(['ollama:11434/api/chat' => Http::sequence()
        ->push(json_encode(['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'search_products', 'arguments' => []]]]], 'done' => true])."\n")
        ->push('Service Unavailable', 503),
    ]);

    expect(fn () => runAssistantConversation('Cuffie?'))
        ->toThrow(AssistantFailedException::class, "Si è verificato un problema con l'assistente. Riprova tra poco.");
});
