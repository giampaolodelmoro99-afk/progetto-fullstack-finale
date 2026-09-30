<?php

use App\Exceptions\Assistant\AssistantFailedException;
use App\Exceptions\Assistant\AssistantTimeoutException;
use App\Exceptions\Assistant\AssistantUnavailableException;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use App\Services\Assistant\FakeAiAssistant;

it('plays the programmed turns in order', function () {
    $fake = (new FakeAiAssistant)
        ->respondWith(new ToolCallRequested('search_products', ['max_price' => 150]))
        ->respondWith('Ti consiglio ', 'le Cuffie Aria [[cuffie-aria]].');

    $first = drainAssistantEvents($fake->stream([['role' => 'user', 'content' => 'Cuffie?']]));
    $second = drainAssistantEvents($fake->stream([['role' => 'user', 'content' => 'Cuffie?']]));

    expect($first)->toEqual([new ToolCallRequested('search_products', ['max_price' => 150])])
        ->and($second)->toEqual([new TextDelta('Ti consiglio '), new TextDelta('le Cuffie Aria [[cuffie-aria]].')]);
});

it('answers with a default text when no turn is programmed', function () {
    $events = drainAssistantEvents((new FakeAiAssistant)->stream([['role' => 'user', 'content' => 'Ciao']]));

    expect($events)->toEqual([new TextDelta("Risposta di prova dell'assistente.")]);
});

it('records messages and tools of every call', function () {
    $fake = new FakeAiAssistant;
    $messages = [['role' => 'user', 'content' => 'Ciao']];
    $tools = [['type' => 'function', 'function' => ['name' => 'get_product']]];

    drainAssistantEvents($fake->stream($messages, $tools));

    expect($fake->calls())->toBe([['messages' => $messages, 'tools' => $tools]]);
});

it('reports itself unavailable and refuses to stream', function () {
    $fake = (new FakeAiAssistant)->unavailable();

    expect($fake->isAvailable())->toBeFalse()
        ->and(fn () => $fake->stream([['role' => 'user', 'content' => 'Ciao']]))->toThrow(AssistantUnavailableException::class)
        ->and($fake->calls())->toBe([]);
});

it('fails halfway after the given number of fragments', function () {
    $fake = (new FakeAiAssistant)->respondWith('Uno ', 'due ', 'tre')->failAfter(2);
    $received = [];

    expect(function () use ($fake, &$received) {
        drainAssistantEvents($fake->stream([['role' => 'user', 'content' => 'Conta']]), $received);
    })
        ->toThrow(AssistantFailedException::class)
        ->and($received)->toEqual([new TextDelta('Uno '), new TextDelta('due ')]);
});

it('times out halfway after the given number of fragments', function () {
    $fake = (new FakeAiAssistant)->respondWith('Uno ', 'due ')->timeoutAfter(1);
    $received = [];

    expect(function () use ($fake, &$received) {
        drainAssistantEvents($fake->stream([['role' => 'user', 'content' => 'Conta']]), $received);
    })
        ->toThrow(AssistantTimeoutException::class, "L'assistente non ha risposto in tempo. Riprova tra poco.")
        ->and($received)->toEqual([new TextDelta('Uno ')]);
});
