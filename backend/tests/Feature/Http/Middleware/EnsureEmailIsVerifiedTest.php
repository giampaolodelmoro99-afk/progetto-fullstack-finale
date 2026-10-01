<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'verified'])
        ->post('/api/v1/test/verified-only', fn () => response()->json(['ok' => true]));
});

it('lets a verified user through', function () {
    actingAsVerifiedCustomer();

    $this->postJson('/api/v1/test/verified-only')->assertOk();
});

it('rejects an unverified user with a json 403 in italian', function () {
    actingAsCustomer();

    $this->postJson('/api/v1/test/verified-only')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Conferma il tuo indirizzo email per continuare.']);
});
