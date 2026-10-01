<?php

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-01 10:30:00');
});

it('rejects a guest', function () {
    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertUnauthorized();
});

it('lists the events of the user from the most recent, 10 per page', function () {
    $user = actingAsVerifiedCustomer();
    $other = User::factory()->create();
    SecurityEvent::factory()->for($other)->count(3)->create(['created_at' => '2026-10-01 10:00:00']);

    foreach (range(1, 12) as $minute) {
        SecurityEvent::factory()->for($user)->create(['created_at' => now()->subMinutes($minute)]);
    }

    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.created_at', '2026-10-01T10:29:00+02:00')
        ->assertJsonPath('data.9.created_at', '2026-10-01T10:20:00+02:00')
        ->assertJsonPath('meta.total', 12)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

    fromFrontend()->getJson('/api/v1/me/security-events?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.created_at', '2026-10-01T10:18:00+02:00')
        ->assertJsonPath('links.next', null);
});

it('orders the events of the same second by id', function () {
    $user = actingAsVerifiedCustomer();
    $first = SecurityEvent::factory()->for($user)->create(['type' => SecurityEventType::Login]);
    $second = SecurityEvent::factory()->for($user)->create(['type' => SecurityEventType::Logout]);

    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.1.id', $first->id);
});

it('shows type, label, device and ip address', function () {
    $user = actingAsVerifiedCustomer();
    $event = SecurityEvent::factory()->for($user)->create([
        'type' => SecurityEventType::PasswordChanged,
        'ip_address' => '93.45.112.18',
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
        'created_at' => '2026-09-20 18:02:00',
    ]);

    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertOk()
        ->assertJsonPath('data', [[
            'id' => $event->id,
            'type' => 'password_changed',
            'label' => 'Password cambiata',
            'device' => 'Safari su iOS',
            'ip_address' => '93.45.112.18',
            'passkey_name' => null,
            'created_at' => '2026-09-20T18:02:00+02:00',
        ]]);
});

it('shows the name of the passkey in the passkey events', function () {
    $user = actingAsVerifiedCustomer();
    SecurityEvent::factory()->for($user)->create([
        'type' => SecurityEventType::PasskeyAdded,
        'metadata' => ['passkey_name' => 'MacBook di Giulia'],
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
    ]);

    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertJsonPath('data.0.type', 'passkey_added')
        ->assertJsonPath('data.0.label', 'Passkey aggiunta')
        ->assertJsonPath('data.0.device', 'Safari su macOS')
        ->assertJsonPath('data.0.passkey_name', 'MacBook di Giulia');
});

it('labels a missing or unknown user agent', function (?string $userAgent) {
    $user = actingAsVerifiedCustomer();
    SecurityEvent::factory()->for($user)->create(['user_agent' => $userAgent]);

    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertJsonPath('data.0.device', 'Dispositivo sconosciuto');
})->with([
    'missing' => [null],
    'unknown' => ['curl/8.5.0'],
]);

it('never exposes the raw user agent nor the metadata', function () {
    $user = actingAsVerifiedCustomer();
    SecurityEvent::factory()->for($user)->create([
        'type' => SecurityEventType::TwoFactorChallengeFailed,
        'metadata' => ['context' => 'login', 'method' => 'totp'],
    ]);

    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertJsonMissingPath('data.0.user_agent')
        ->assertJsonMissingPath('data.0.metadata');
});

it('returns an empty list without events', function () {
    actingAsVerifiedCustomer();

    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});
