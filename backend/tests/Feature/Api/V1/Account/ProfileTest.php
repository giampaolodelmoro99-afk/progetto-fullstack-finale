<?php

use App\Models\User;

it('returns the authenticated user without secrets', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = User::factory()->withTwoFactor()->create([
        'name' => 'Davide Conti',
        'email' => 'davide.conti@esempio.it',
        'email_verified_at' => '2026-02-01 09:00:00',
    ]);
    actingAsCustomer($user);

    fromFrontend()->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $user->id,
                'name' => 'Davide Conti',
                'email' => 'davide.conti@esempio.it',
                'email_verified_at' => '2026-02-01T09:00:00+01:00',
                'role' => ['value' => 'customer', 'label' => 'Cliente'],
                'pending_email_change' => null,
                'created_at' => '2026-10-01T10:30:00+02:00',
            ],
        ]);
});

it('shows the pending email change', function () {
    $this->travelTo('2026-10-01 10:30:00');
    $user = User::factory()->withPendingEmailChange('davide.nuovo@esempio.it')->create();
    actingAsCustomer($user);

    fromFrontend()->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.pending_email_change.new_email', 'davide.nuovo@esempio.it')
        ->assertJsonPath('data.pending_email_change.expires_at', '2026-10-01T11:30:00+02:00');
});

it('shows the admin role', function () {
    actingAsAdmin();

    fromFrontend()->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.role.value', 'admin')
        ->assertJsonPath('data.role.label', 'Amministrazione');
});

it('updates only the name', function () {
    $user = actingAsVerifiedCustomer();
    $email = $user->email;

    fromFrontend()->patchJson('/api/v1/me', ['name' => '  Giulia Ferri Rossi ', 'email' => 'altro@esempio.it'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Giulia Ferri Rossi')
        ->assertJsonPath('data.email', $email);

    expect($user->fresh()->name)->toBe('Giulia Ferri Rossi')
        ->and($user->fresh()->email)->toBe($email);
});

it('validates the name', function (mixed $name) {
    actingAsVerifiedCustomer();

    fromFrontend()->patchJson('/api/v1/me', ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
})->with([
    'missing' => '',
    'only spaces' => '   ',
    '256 characters' => str_repeat('a', 256),
]);

it('rejects a guest', function (string $method) {
    fromFrontend()->json($method, '/api/v1/me', ['name' => 'Mario Rossi'])->assertUnauthorized();
})->with(['GET', 'PATCH']);
