<?php

beforeEach(function () {
    $this->freezeTime();
});

it('limits a guest to 10 messages per minute', function () {
    foreach (range(1, 10) as $attempt) {
        askAssistant('Ciao')->assertOk()->streamedContent();
    }

    askAssistant('Ciao')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertExactJson(['message' => 'Troppi tentativi. Riprova tra 60 secondi.']);
});

it('counts the guests by ip address', function () {
    foreach (range(1, 10) as $attempt) {
        askAssistant('Ciao')->assertOk()->streamedContent();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);

    askAssistant('Ciao')->assertOk()->streamedContent();
});

it('limits an authenticated customer to 10 messages per minute', function () {
    actingAsVerifiedCustomer();

    foreach (range(1, 10) as $attempt) {
        askAssistant('Ciao')->assertOk()->streamedContent();
    }

    askAssistant('Ciao')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
});

it('counts each authenticated customer separately', function () {
    actingAsVerifiedCustomer();

    foreach (range(1, 10) as $attempt) {
        askAssistant('Ciao')->assertOk()->streamedContent();
    }

    actingAsVerifiedCustomer();

    askAssistant('Ciao')->assertOk()->streamedContent();
});

it('opens again after a minute', function () {
    foreach (range(1, 10) as $attempt) {
        askAssistant('Ciao')->assertOk()->streamedContent();
    }

    $this->travel(61)->seconds();

    askAssistant('Ciao')->assertOk()->streamedContent();
});
