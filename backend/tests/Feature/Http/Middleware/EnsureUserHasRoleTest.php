<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'role:admin'])
        ->get('/api/v1/test/admin-only', fn () => response()->json(['ok' => true]));
});

it('lets an admin through', function () {
    actingAsAdmin();

    $this->getJson('/api/v1/test/admin-only')->assertOk();
});

it('rejects a customer with a json 403 in italian', function () {
    actingAsVerifiedCustomer();

    $this->getJson('/api/v1/test/admin-only')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Non hai i permessi per questa operazione.']);
});

it('rejects a guest with 401', function () {
    $this->getJson('/api/v1/test/admin-only')->assertUnauthorized();
});
