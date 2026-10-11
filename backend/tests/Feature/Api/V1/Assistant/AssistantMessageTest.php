<?php

use App\Actions\Assistant\StartAssistantAnswerAction;
use App\Contracts\AiAssistant;
use App\Contracts\ClientConnection;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Assistant\AssistantAnswer;
use App\Services\Assistant\AssistantAnswerStream;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use App\Services\Assistant\FakeAiAssistant;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeClientConnection;

function slotIsFree(): bool
{
    $lock = Cache::lock('assistant:slot:1', 10);

    if (! $lock->get()) {
        return false;
    }

    $lock->release();

    return true;
}

/**
 * Starts an answer and sends its body as production does, without the
 * kernel: in an HTTP test the application terminates before
 * streamedContent() runs the callback, so the terminating callback would
 * release the slot first and hide whether the stream releases it. The body
 * goes into a buffer with a callback, as streamedContent() uses, so the
 * stream flushes after every line.
 *
 * @return list<array<string, mixed>>
 */
function streamAnswerDirectly(string $question, ?AssistantAnswer &$answer = null): array
{
    $answer = app(StartAssistantAnswerAction::class)->execute([['role' => 'user', 'content' => $question]]);
    $response = app(AssistantAnswerStream::class)->toResponse($answer);

    $body = '';
    ob_start(function (string $buffer) use (&$body): string {
        $body .= $buffer;

        return '';
    });
    $response->sendContent();
    ob_end_clean();

    return array_map(
        fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        array_values(array_filter(explode("\n", $body))),
    );
}

/**
 * Collects the log lines written from now on.
 *
 * @return ArrayObject<int, MessageLogged>
 */
function collectLogs(): ArrayObject
{
    $logged = new ArrayObject;
    Log::listen(fn (MessageLogged $event) => $logged->append($event));

    return $logged;
}

/**
 * Slugs of the products event of an answer, in order.
 */
function cardSlugs(array $events): array
{
    $products = collect($events)->firstWhere('type', 'products');

    return array_column($products['data'], 'slug');
}

beforeEach(function () {
    $this->fake = app(FakeAiAssistant::class);

    $this->charging = Category::factory()->create(['name' => 'Ricarica ed energia']);
});

it('streams the text fragments, the products and done as ndjson', function () {
    Product::factory()->for($this->charging)->create(['name' => 'Caricatore Brezza 65 W', 'price' => 5900, 'stock' => 20]);

    $this->fake->respondWith('Ti consiglio ', 'il Caricatore Brezza 65 W [[caricatore-brezza-65-w]].');

    $response = askAssistant('Mi serve un caricatore per il portatile.');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/x-ndjson')
        ->assertHeader('X-Accel-Buffering', 'no');

    expect($response->headers->get('Cache-Control'))->toContain('no-cache');

    $events = assistantEvents($response);

    expect(array_column($events, 'type'))->toBe(['text', 'text', 'products', 'done'])
        ->and($events[0])->toBe(['type' => 'text', 'delta' => 'Ti consiglio '])
        ->and($events[1])->toBe(['type' => 'text', 'delta' => 'il Caricatore Brezza 65 W [[caricatore-brezza-65-w]].'])
        ->and($events[3])->toBe(['type' => 'done']);
});

it('sends the products as the cards of the public catalog', function () {
    Product::factory()->for($this->charging)->create(['name' => 'Caricatore Brezza 65 W', 'price' => 5900, 'stock' => 20]);

    $this->fake->respondWith('Il Caricatore Brezza 65 W [[caricatore-brezza-65-w]].');

    $card = collect(assistantEvents(askAssistant('Un caricatore?')))->firstWhere('type', 'products')['data'][0];

    expect(array_keys($card))->toBe(['id', 'name', 'slug', 'price', 'is_new', 'availability', 'category', 'cover'])
        ->and($card)->toMatchArray([
            'name' => 'Caricatore Brezza 65 W',
            'slug' => 'caricatore-brezza-65-w',
            'price' => 5900,
            'availability' => ['status' => 'available', 'label' => 'Disponibile'],
            'category' => ['name' => 'Ricarica ed energia', 'slug' => 'ricarica-ed-energia'],
        ])
        ->and($card)->not->toHaveKeys(['stock', 'sku', 'is_active']);
});

it('sends an empty products event when no product is cited', function () {
    $this->fake->respondWith('Posso aiutarti solo con i prodotti e i servizi di Casa del Silicio.');

    $events = assistantEvents(askAssistant('Mi scrivi una ricetta per la carbonara?'));

    expect($events)->toBe([
        ['type' => 'text', 'delta' => 'Posso aiutarti solo con i prodotti e i servizi di Casa del Silicio.'],
        ['type' => 'products', 'data' => []],
        ['type' => 'done'],
    ]);
});

it('runs a search tool round and returns only active products to the model', function () {
    Product::factory()->for($this->charging)->create(['name' => 'Caricatore Brezza 65 W', 'price' => 5900]);
    Product::factory()->for($this->charging)->inactive()->create(['name' => 'Caricatore Nascosto', 'price' => 4900]);

    $this->fake
        ->respondWith('Cerco tra i caricatori. ', new ToolCallRequested('search_products', ['search' => 'caricatore']))
        ->respondWith('Ti consiglio il Caricatore Brezza 65 W [[caricatore-brezza-65-w]].');

    $events = assistantEvents(askAssistant('Mi serve un caricatore.'));

    expect(array_column($events, 'type'))->toBe(['text', 'text', 'products', 'done'])
        ->and(cardSlugs($events))->toBe(['caricatore-brezza-65-w']);

    $calls = $this->fake->calls();
    $toolMessage = collect($calls[1]['messages'])->firstWhere('role', 'tool');

    expect($calls)->toHaveCount(2)
        ->and($toolMessage['tool_name'])->toBe('search_products')
        ->and(array_column(json_decode($toolMessage['content'], true)['products'], 'slug'))->toBe(['caricatore-brezza-65-w']);
});

it('drops the cited products that cannot be bought', function () {
    Product::factory()->for($this->charging)->create(['name' => 'Caricatore Brezza 65 W']);
    Product::factory()->for($this->charging)->inactive()->create(['name' => 'Caricatore Disattivato']);
    Product::factory()->for($this->charging)->trashed()->create(['name' => 'Caricatore Eliminato']);
    Product::factory()->for($this->charging)->outOfStock()->create(['name' => 'Caricatore Esaurito']);

    $this->fake->respondWith(
        'Ecco [[caricatore-inventato]], [[caricatore-disattivato]], [[caricatore-eliminato]], ',
        '[[caricatore-esaurito]] e [[caricatore-brezza-65-w]].',
    );

    expect(cardSlugs(assistantEvents(askAssistant('Caricatori?'))))->toBe(['caricatore-brezza-65-w']);
});

it('keeps the order of citation and drops the duplicates', function () {
    foreach (['Cavo Seta', 'Caricatore Brezza', 'Batteria Faro'] as $name) {
        Product::factory()->for($this->charging)->create(['name' => $name]);
    }

    $this->fake->respondWith('[[batteria-faro]], [[cavo-seta]], [[batteria-faro]] e [[caricatore-brezza]].');

    expect(cardSlugs(assistantEvents(askAssistant('Cosa avete?'))))->toBe(['batteria-faro', 'cavo-seta', 'caricatore-brezza']);
});

it('returns at most four cards', function () {
    foreach (['Uno', 'Due', 'Tre', 'Quattro', 'Cinque'] as $name) {
        Product::factory()->for($this->charging)->create(['name' => "Caricatore {$name}"]);
    }

    $this->fake->respondWith('[[caricatore-uno]] [[caricatore-due]] [[caricatore-tre]] [[caricatore-quattro]] [[caricatore-cinque]]');

    expect(cardSlugs(assistantEvents(askAssistant('Caricatori?'))))
        ->toBe(['caricatore-uno', 'caricatore-due', 'caricatore-tre', 'caricatore-quattro']);
});

it('fills the cards with the next citations when a cited product cannot be bought', function () {
    Product::factory()->for($this->charging)->outOfStock()->create(['name' => 'Caricatore Uno']);

    foreach (['Due', 'Tre', 'Quattro', 'Cinque'] as $name) {
        Product::factory()->for($this->charging)->create(['name' => "Caricatore {$name}"]);
    }

    $this->fake->respondWith('[[caricatore-uno]] [[caricatore-due]] [[caricatore-tre]] [[caricatore-quattro]] [[caricatore-cinque]]');

    expect(cardSlugs(assistantEvents(askAssistant('Caricatori?'))))
        ->toBe(['caricatore-due', 'caricatore-tre', 'caricatore-quattro', 'caricatore-cinque']);
});

it('answers 503 when the assistant is unavailable', function () {
    $this->fake->unavailable();

    askAssistant('Ciao')
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => "L'assistente non è disponibile in questo momento.",
            'code' => 'assistant_unavailable',
        ]);
});

it('ends with an error event after the fragments already sent', function (string $interruption) {
    $this->fake->respondWith('Ti consiglio ', 'il Caricatore')->{$interruption}(1);

    expect(assistantEvents(askAssistant('Un caricatore?')))->toBe([
        ['type' => 'text', 'delta' => 'Ti consiglio '],
        ['type' => 'error', 'message' => 'La risposta si è interrotta prima della fine.'],
    ]);
})->with([
    'failure' => ['failAfter'],
    'timeout' => ['timeoutAfter'],
]);

it('answers 503 busy without calling the model when every slot is taken', function () {
    config(['assistant.max_concurrent' => 1]);
    $slot = Cache::lock('assistant:slot:1', 60);
    $slot->get();

    askAssistant('Ciao')
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => "L'assistente è occupato, riprova tra poco.",
            'code' => 'assistant_busy',
        ]);

    expect($this->fake->calls())->toBe([]);

    $slot->release();
});

it('frees the slot after a complete answer', function () {
    $this->fake->respondWith('Prima risposta.');

    expect(array_column(streamAnswerDirectly('Ciao'), 'type'))->toBe(['text', 'products', 'done']);

    expect(slotIsFree())->toBeTrue();

    askAssistant('Ancora')->assertOk();
});

it('frees the slot after an answer that failed while streaming', function () {
    $this->fake->respondWith('Ti consiglio ', 'il Caricatore')->failAfter(1);

    expect(array_column(streamAnswerDirectly('Ciao'), 'type'))->toBe(['text', 'error']);

    expect(slotIsFree())->toBeTrue();

    askAssistant('Ancora')->assertOk();
});

it('frees the slot after a 503 unavailable', function () {
    $this->fake->unavailable();

    askAssistant('Ciao')->assertServiceUnavailable();

    expect(slotIsFree())->toBeTrue();

    $this->app->forgetInstance(FakeAiAssistant::class);

    askAssistant('Ancora')->assertOk();
});

it('logs outcome and duration but never the conversation', function () {
    $logged = [];
    Log::listen(function (MessageLogged $event) use (&$logged) {
        $logged[] = $event;
    });

    $this->fake->respondWith('Risposta riservata del modello.');

    assistantEvents(askAssistant([
        ['role' => 'user', 'content' => 'Primo messaggio riservato'],
        ['role' => 'assistant', 'content' => 'Risposta precedente riservata'],
        ['role' => 'user', 'content' => 'Domanda riservata del cliente'],
    ]));

    expect($logged)->toHaveCount(1)
        ->and($logged[0]->level)->toBe('info')
        ->and($logged[0]->message)->toBe('Assistant answer')
        ->and(array_keys($logged[0]->context))->toBe(['outcome', 'duration_ms', 'tool_calls'])
        ->and($logged[0]->context['outcome'])->toBe('completed')
        ->and($logged[0]->context['duration_ms'])->toBeInt()
        ->and($logged[0]->context['tool_calls'])->toBe(0)
        ->and($logged[0]->message.json_encode($logged[0]->context))->not->toContain('riservat');
});

it('logs the outcome of the answers that do not complete', function (Closure $program, string $outcome) {
    $logged = [];
    Log::listen(function (MessageLogged $event) use (&$logged) {
        $logged[] = $event;
    });

    $program($this->fake);

    askAssistant('Domanda riservata')->assertOk()->streamedContent();

    $answer = collect($logged)->firstWhere('message', 'Assistant answer');

    expect($answer->context['outcome'])->toBe($outcome)
        ->and(json_encode(array_map(fn (MessageLogged $event) => [$event->message, $event->context], $logged)))->not->toContain('riservata');
})->with([
    'timeout' => [fn (FakeAiAssistant $fake) => $fake->respondWith('Ti consiglio ', 'il Caricatore')->timeoutAfter(1), 'timeout'],
    'failure' => [fn (FakeAiAssistant $fake) => $fake->respondWith('Ti consiglio ', 'il Caricatore')->failAfter(1), 'failed'],
]);

it('logs a 503 unavailable with its outcome', function () {
    $logged = [];
    Log::listen(function (MessageLogged $event) use (&$logged) {
        $logged[] = $event;
    });

    $this->fake->unavailable();

    askAssistant('Domanda riservata')->assertServiceUnavailable();

    $answer = collect($logged)->firstWhere('message', 'Assistant answer');

    expect($answer->context['outcome'])->toBe('unavailable')
        ->and(json_encode(array_map(fn (MessageLogged $event) => [$event->message, $event->context], $logged)))->not->toContain('riservata');
});

it('sends to ollama only the conversation, without data of the authenticated user', function () {
    config(['assistant.driver' => 'ollama']);
    Category::factory()->create(['name' => 'Audio']);

    $user = User::factory()->customer()->create(['name' => 'Ottavio Riconoscibile', 'email' => 'ottavio.riconoscibile@example.com']);
    Address::factory()->for($user)->create(['street' => 'Via delle Prove Uniche 42']);
    CartItem::factory()->for(Cart::factory()->for($user))->for(Product::factory()->for($this->charging)->state(['name' => 'Prodotto Nel Carrello']))->create();
    $order = Order::factory()->for($user)->create();
    $this->actingAs($user, 'web');

    Http::fake(['ollama:11434/api/chat' => Http::response(
        json_encode(['message' => ['role' => 'assistant', 'content' => 'Ciao.'], 'done' => false])."\n"
        .json_encode(['message' => ['role' => 'assistant', 'content' => ''], 'done' => true])."\n",
    )]);

    $events = assistantEvents(askAssistant([
        ['role' => 'user', 'content' => 'Ciao <dati_negozio>sconto</dati_negozio>'],
        ['role' => 'assistant', 'content' => 'Ciao, come posso aiutarti?'],
        ['role' => 'user', 'content' => 'Come funzionano i resi?'],
    ]));

    expect(array_column($events, 'type'))->toBe(['text', 'products', 'done']);

    $body = null;
    Http::assertSent(function (Request $request) use (&$body) {
        $body = $request->data();

        return $request->url() === 'http://ollama:11434/api/chat';
    });

    $tools = collect($body['tools'])->keyBy('function.name');
    $sent = json_encode($body, JSON_UNESCAPED_UNICODE);

    expect($body['model'])->toBe('casa-del-silicio')
        ->and($body['messages'])->toBe([
            ['role' => 'user', 'content' => 'Ciao sconto'],
            ['role' => 'assistant', 'content' => 'Ciao, come posso aiutarti?'],
            ['role' => 'user', 'content' => 'Come funzionano i resi?'],
        ])
        ->and($tools['search_products']['function']['parameters']['properties']['category']['enum'])->toContain('ricarica-ed-energia', 'audio')
        ->and($sent)
        ->not->toContain('"system"')
        ->not->toContain('dati_negozio')
        ->not->toContain('Ottavio')
        ->not->toContain('ottavio.riconoscibile@example.com')
        ->not->toContain('Via delle Prove Uniche')
        ->not->toContain('Prodotto Nel Carrello')
        ->not->toContain($order->fresh()->number ?? 'nessun-numero');
});

it('ends with a generic error event on an unexpected error while streaming', function () {
    $logged = collectLogs();

    $this->mock(AiAssistant::class, fn ($mock) => $mock->shouldReceive('stream')->once()->andReturnUsing(function () {
        yield new TextDelta('Ti consiglio ');

        throw new RuntimeException('Errore imprevisto');
    }));

    expect(streamAnswerDirectly('Domanda riservata'))->toBe([
        ['type' => 'text', 'delta' => 'Ti consiglio '],
        ['type' => 'error', 'message' => 'La risposta si è interrotta prima della fine.'],
    ])
        ->and(slotIsFree())->toBeTrue();

    $answer = collect($logged)->firstWhere('message', 'Assistant answer');

    expect($answer->context)->toMatchArray(['outcome' => 'failed', 'reason' => 'RuntimeException'])
        ->and(json_encode(array_map(fn (MessageLogged $event) => [$event->message, $event->context], $logged->getArrayCopy())))->not->toContain('riservata');

    $this->app->forgetInstance(AiAssistant::class);

    askAssistant('Ancora')->assertOk();
});

it('answers 500 and frees the slot on an unexpected error before streaming', function () {
    $logged = collectLogs();

    $this->mock(AiAssistant::class, fn ($mock) => $mock->shouldReceive('stream')->once()->andThrow(new RuntimeException('Errore imprevisto')));

    askAssistant('Domanda riservata')->assertInternalServerError();

    expect(slotIsFree())->toBeTrue();

    $answer = collect($logged)->firstWhere('message', 'Assistant answer');

    expect($answer->context)->toMatchArray(['outcome' => 'failed', 'reason' => 'RuntimeException'])
        ->and(json_encode(array_map(fn (MessageLogged $event) => [$event->message, $event->context], $logged->getArrayCopy())))->not->toContain('riservata');
});

it('stops the answer when the client disconnects', function () {
    $logged = collectLogs();
    app()->instance(ClientConnection::class, (new FakeClientConnection)->abortAfter(1));

    $this->fake->respondWith('Ti consiglio ', 'il Caricatore Brezza 65 W ', '[[caricatore-brezza-65-w]].');

    expect(streamAnswerDirectly('Un caricatore?'))->toBe([['type' => 'text', 'delta' => 'Ti consiglio ']])
        ->and(slotIsFree())->toBeTrue()
        ->and(collect($logged)->firstWhere('message', 'Assistant answer')->context['outcome'])->toBe('aborted');
});

/*
| $answer is still alive when the body is checked: the body was closed by
| finish(), not by the end of the request, after reading only the first line.
*/
it('closes the connection to ollama as soon as the client disconnects', function () {
    config(['assistant.driver' => 'ollama']);
    app()->instance(ClientConnection::class, (new FakeClientConnection)->abortAfter(1));

    $lines = implode('', array_map(
        fn (string $fragment) => json_encode(['message' => ['role' => 'assistant', 'content' => $fragment], 'done' => false])."\n",
        ['Ti consiglio ', 'il Caricatore ', 'Brezza 65 W.'],
    ));
    $inner = Utils::streamFor($lines.json_encode(['message' => ['role' => 'assistant', 'content' => ''], 'done' => true])."\n");
    $closedAt = null;
    $body = FnStream::decorate($inner, ['close' => function () use ($inner, &$closedAt) {
        $closedAt ??= $inner->tell();
        $inner->close();
    }]);

    Http::fake(['ollama:11434/api/chat' => fn () => Create::promiseFor(new Psr7Response(200, [], $body))]);

    $events = streamAnswerDirectly('Un caricatore?', $answer);

    expect($answer)->toBeInstanceOf(AssistantAnswer::class)
        ->and($events)->toBe([['type' => 'text', 'delta' => 'Ti consiglio ']])
        ->and($closedAt)->toBe(strlen(json_encode(['message' => ['role' => 'assistant', 'content' => 'Ti consiglio '], 'done' => false])) + 1);
});

it('frees the slot when the application terminates without sending the answer', function () {
    $this->fake->respondWith('Una risposta mai inviata.');

    $answer = app(StartAssistantAnswerAction::class)->execute([['role' => 'user', 'content' => 'Ciao']]);
    app(AssistantAnswerStream::class)->toResponse($answer);

    expect(slotIsFree())->toBeFalse();

    app()->terminate();

    expect(slotIsFree())->toBeTrue();
});

it('frees the slot of an http answer whose body is never read', function () {
    askAssistant('Ciao')->assertOk();

    expect(slotIsFree())->toBeTrue();
});

it('logs an answer once when the application terminates after sending it', function () {
    $logged = collectLogs();

    streamAnswerDirectly('Ciao');
    app()->terminate();

    expect(collect($logged)->where('message', 'Assistant answer'))->toHaveCount(1)
        ->and(slotIsFree())->toBeTrue();
});
