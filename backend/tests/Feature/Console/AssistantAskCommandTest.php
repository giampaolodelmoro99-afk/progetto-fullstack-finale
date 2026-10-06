<?php

use App\Contracts\AiAssistant;
use App\Models\Category;
use App\Models\Product;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use App\Services\Assistant\FakeAiAssistant;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function assistantSlotIsFree(): bool
{
    $lock = Cache::lock('assistant:slot:1');

    if (! $lock->get()) {
        return false;
    }

    $lock->release();

    return true;
}

beforeEach(function () {
    $this->fake = app(FakeAiAssistant::class);
});

it('prints the streamed answer and the tool calls', function () {
    Product::factory()->for(Category::factory()->create(['name' => 'Audio']))->create(['name' => 'Cuffie Aria']);

    $this->fake
        ->respondWith(new ToolCallRequested('search_products', ['category' => 'audio']))
        ->respondWith('Ti consiglio ', 'le Cuffie Aria [[cuffie-aria]].');

    $this->artisan('assistant:ask', ['question' => ['Cerco', 'cuffie']])
        ->expectsOutputToContain('→ search_products {"category":"audio"}')
        ->expectsOutputToContain('← 1 prodotto: cuffie-aria')
        ->expectsOutputToContain('Ti consiglio ')
        ->expectsOutputToContain('le Cuffie Aria [[cuffie-aria]].')
        ->assertSuccessful();

    expect($this->fake->calls()[0]['messages'])->toBe([['role' => 'user', 'content' => 'Cerco cuffie']])
        ->and(assistantSlotIsFree())->toBeTrue();
});

it('prints the shop rules requested by the model', function () {
    $this->fake
        ->respondWith(new ToolCallRequested('get_shop_info', ['section' => 'garanzia']))
        ->respondWith('La garanzia dura 2 anni.');

    $this->artisan('assistant:ask', ['question' => ['Quanto', 'dura', 'la', 'garanzia?']])
        ->expectsOutputToContain('→ get_shop_info {"section":"garanzia"}')
        ->expectsOutputToContain('← regole: Garanzia')
        ->expectsOutputToContain('La garanzia dura 2 anni.')
        ->assertSuccessful();
});

it('prints the timing when requested', function () {
    $this->artisan('assistant:ask', ['question' => ['Ciao'], '--timing' => true])
        ->expectsOutputToContain('Prima parola')
        ->expectsOutputToContain('Totale')
        ->assertSuccessful();
});

it('refuses without calling the model when the slot is taken', function () {
    $held = Cache::lock('assistant:slot:1', 60);
    $held->get();

    $this->artisan('assistant:ask', ['question' => ['Ciao']])
        ->expectsOutputToContain("L'assistente è occupato, riprova tra poco.")
        ->assertFailed();

    expect($this->fake->calls())->toBe([]);

    $held->release();
});

it('releases the slot after a complete answer', function () {
    $this->artisan('assistant:ask', ['question' => ['Ciao']])->assertSuccessful();

    expect(assistantSlotIsFree())->toBeTrue();

    $this->artisan('assistant:ask', ['question' => ['Ciao']])->assertSuccessful();
});

it('releases the slot after an error halfway through the answer', function () {
    $this->fake->respondWith('Ti consiglio ', 'le Cuffie Aria.')->failAfter(1);

    $this->artisan('assistant:ask', ['question' => ['Ciao']])
        ->expectsOutputToContain('Ti consiglio')
        ->expectsOutputToContain("Si è verificato un problema con l'assistente. Riprova tra poco.")
        ->assertFailed();

    expect(assistantSlotIsFree())->toBeTrue();

    $this->artisan('assistant:ask', ['question' => ['Ciao']])->assertSuccessful();
});

it('releases the slot after a timeout', function () {
    $this->fake->respondWith('Ti consiglio ')->timeoutAfter(1);

    $this->artisan('assistant:ask', ['question' => ['Ciao']])
        ->expectsOutputToContain("L'assistente non ha risposto in tempo. Riprova tra poco.")
        ->assertFailed();

    expect(assistantSlotIsFree())->toBeTrue();
});

it('releases the slot when the assistant is unavailable', function () {
    $this->fake->unavailable();

    $this->artisan('assistant:ask', ['question' => ['Ciao']])
        ->expectsOutputToContain("L'assistente non è disponibile in questo momento.")
        ->assertFailed();

    expect(assistantSlotIsFree())->toBeTrue();

    app()->forgetInstance(FakeAiAssistant::class);

    $this->artisan('assistant:ask', ['question' => ['Ciao']])->assertSuccessful();
});

it('reports ollama unreachable as unavailable and releases the slot', function () {
    config(['assistant.driver' => 'ollama']);
    Http::fake(['ollama:11434/*' => Http::failedConnection()]);

    $this->artisan('assistant:ask', ['question' => ['Ciao']])
        ->expectsOutputToContain("L'assistente non è disponibile in questo momento.")
        ->assertFailed();

    expect(assistantSlotIsFree())->toBeTrue();
});

it('reports an unexpected error with the generic message and releases the slot', function () {
    $errors = new ArrayObject;
    Log::listen(function (MessageLogged $event) use ($errors): void {
        if ($event->level === 'error') {
            $errors->append($event->message);
        }
    });

    $this->mock(AiAssistant::class, fn ($mock) => $mock->shouldReceive('stream')->once()->andReturnUsing(function () {
        yield new TextDelta('Ti consiglio ');

        throw new RuntimeException('Errore imprevisto');
    }));

    $this->artisan('assistant:ask', ['question' => ['Ciao']])
        ->expectsOutputToContain('Ti consiglio')
        ->expectsOutputToContain("Si è verificato un problema con l'assistente. Riprova tra poco.")
        ->assertFailed();

    expect(assistantSlotIsFree())->toBeTrue()
        ->and($errors->getArrayCopy())->toBe(['Errore imprevisto']);
});
