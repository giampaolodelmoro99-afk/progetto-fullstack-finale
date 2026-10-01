<?php

it('limits a guest to 120 api requests per minute', function () {
    $this->freezeTime();

    foreach (range(1, 120) as $attempt) {
        $this->getJson('/api/v1/health')->assertOk();
    }

    $this->getJson('/api/v1/health')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60')
        ->assertExactJson(['message' => 'Troppi tentativi. Riprova tra 60 secondi.']);
});

it('counts the requests of each authenticated user separately', function () {
    actingAsVerifiedCustomer();

    foreach (range(1, 120) as $attempt) {
        $this->getJson('/api/v1/health')->assertOk();
    }

    actingAsVerifiedCustomer();

    $this->getJson('/api/v1/health')->assertOk();
});

it('lets the frontend read the Retry-After header', function () {
    expect(config('cors.exposed_headers'))->toBe(['Retry-After']);
});
