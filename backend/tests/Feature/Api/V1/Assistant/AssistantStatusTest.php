<?php

use App\Services\Assistant\FakeAiAssistant;
use Illuminate\Support\Facades\Http;

it('reports the assistant as available', function () {
    $this->getJson('/api/v1/assistant/status')
        ->assertOk()
        ->assertExactJson(['data' => ['available' => true]]);
});

it('reports the assistant as unavailable', function () {
    app(FakeAiAssistant::class)->unavailable();

    $this->getJson('/api/v1/assistant/status')
        ->assertOk()
        ->assertExactJson(['data' => ['available' => false]]);
});

it('keeps the status in the cache for 10 seconds', function () {
    $this->freezeTime();

    $this->getJson('/api/v1/assistant/status')->assertJsonPath('data.available', true);

    app(FakeAiAssistant::class)->unavailable();
    $this->travel(9)->seconds();

    $this->getJson('/api/v1/assistant/status')->assertJsonPath('data.available', true);

    $this->travel(2)->seconds();

    $this->getJson('/api/v1/assistant/status')->assertJsonPath('data.available', false);
});

it('checks ollama and the shop model with the ollama driver', function () {
    config(['assistant.driver' => 'ollama']);
    Http::fake(['ollama:11434/api/tags' => Http::response(['models' => [['name' => 'casa-del-silicio:latest']]])]);

    $this->getJson('/api/v1/assistant/status')->assertExactJson(['data' => ['available' => true]]);
});

it('reports the assistant as unavailable when ollama cannot be reached', function () {
    config(['assistant.driver' => 'ollama']);
    Http::fake(['ollama:11434/*' => Http::failedConnection()]);

    $this->getJson('/api/v1/assistant/status')->assertExactJson(['data' => ['available' => false]]);
});
