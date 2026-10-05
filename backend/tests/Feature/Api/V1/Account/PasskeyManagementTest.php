<?php

use App\Enums\SecurityEventType;
use App\Models\Passkey;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\PasskeyAddedNotification;
use App\Notifications\PasskeyRemovedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-05 10:00:00');
});

it('rejects a guest on every route', function (string $method, string $uri) {
    $passkey = Passkey::factory()->create();

    fromFrontend()->json($method, str_replace('{passkey}', (string) $passkey->id, $uri))
        ->assertUnauthorized();
})->with([
    ['GET', '/api/v1/me/passkeys'],
    ['POST', '/api/v1/me/passkeys/options'],
    ['POST', '/api/v1/me/passkeys'],
    ['PATCH', '/api/v1/me/passkeys/{passkey}'],
    ['DELETE', '/api/v1/me/passkeys/{passkey}'],
]);

it('lists the passkeys of the user from the most recent, without the credential', function () {
    $user = actingAsVerifiedCustomer();
    Passkey::factory()->for($user)->create(['name' => 'MacBook di Giulia', 'created_at' => '2026-09-20 18:02:00', 'last_used_at' => '2026-10-04 08:15:00']);
    $this->travelTo('2026-10-05 10:00:00');
    Passkey::factory()->for($user)->create(['name' => 'iPhone di Giulia', 'created_at' => '2026-10-01 09:30:00']);
    Passkey::factory()->create(['name' => 'Passkey di un altro cliente']);

    $response = fromFrontend()->getJson('/api/v1/me/passkeys')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.0'))->toBe([
        'id' => $user->passkeys()->where('name', 'iPhone di Giulia')->value('id'),
        'name' => 'iPhone di Giulia',
        'created_at' => '2026-10-01T09:30:00+02:00',
        'last_used_at' => null,
    ])->and($response->json('data.1'))->toBe([
        'id' => $user->passkeys()->where('name', 'MacBook di Giulia')->value('id'),
        'name' => 'MacBook di Giulia',
        'created_at' => '2026-09-20T18:02:00+02:00',
        'last_used_at' => '2026-10-04T08:15:00+02:00',
    ]);

    expect($response->getContent())->not->toContain('credential')
        ->not->toContain('Y3JlZGVudGlhbC');
});

it('asks for the identity confirmation before the registration', function (string $uri, bool $twoFactor, array $requires) {
    actingAsCustomer($twoFactor ? userWithTwoFactor() : User::factory()->create());

    fromFrontend()->postJson($uri, ['name' => 'iPhone di Giulia', 'credential' => passkeyRegistrationAnswer()])
        ->assertStatus(423)
        ->assertJsonPath('requires', $requires);
})->with([
    'options without two-factor authentication' => ['/api/v1/me/passkeys/options', false, ['password']],
    'options with two-factor authentication' => ['/api/v1/me/passkeys/options', true, ['password', 'second_factor']],
    'save' => ['/api/v1/me/passkeys', false, ['password']],
]);

it('requires a discoverable credential and the user verification in the registration options', function () {
    $user = actingAsCustomer(User::factory()->create(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it']));
    passkeyFor($user);
    confirmIdentity();

    $options = fromFrontend()->postJson('/api/v1/me/passkeys/options')
        ->assertOk()
        ->json('data.options');

    expect($options['rp'])->toBe(['id' => 'localhost', 'name' => 'localhost'])
        ->and($options['authenticatorSelection'])->toBe([
            'requireResidentKey' => true,
            'userVerification' => 'required',
            'residentKey' => 'required',
        ])
        ->and($options['user']['name'])->toBe('giulia.ferri@esempio.it')
        ->and($options['user']['displayName'])->toBe('Giulia Ferri')
        ->and($options['attestation'])->toBe('none')
        ->and($options['timeout'])->toBe(60000)
        ->and($options['excludeCredentials'])->toBe([['type' => 'public-key', 'id' => 'Y3JlZGVudGlhbC1naXVsaWE']])
        ->and(strlen(base64_decode(strtr($options['challenge'], '-_', '+/'))))->toBe(32);
});

it('registers a passkey with its event and notice', function () {
    fakePasskeys();
    $user = actingAsVerifiedCustomer();
    confirmIdentity();
    fromFrontend()->postJson('/api/v1/me/passkeys/options')->assertOk();

    fromFrontend()->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15')
        ->postJson('/api/v1/me/passkeys', ['name' => '  iPhone di Giulia  ', 'credential' => passkeyRegistrationAnswer()])
        ->assertCreated()
        ->assertExactJson(['data' => [
            'id' => Passkey::sole()->id,
            'name' => 'iPhone di Giulia',
            'created_at' => '2026-10-05T10:00:00+02:00',
            'last_used_at' => null,
        ]]);

    $event = SecurityEvent::sole();

    expect(Passkey::sole()->user_id)->toBe($user->id)
        ->and(Passkey::sole()->credential_id)->toBe('bnVvdmEtcGFzc2tleQ')
        ->and($event->type)->toBe(SecurityEventType::PasskeyAdded)
        ->and($event->metadata)->toBe(['passkey_name' => 'iPhone di Giulia'])
        ->and($user->fresh()->two_factor_recovery_codes)->toBeNull();

    Notification::assertSentTo($user, PasskeyAddedNotification::class, fn (PasskeyAddedNotification $notification) => $notification->passkeyName === 'iPhone di Giulia'
        && $notification->device === 'Safari su macOS'
        && $notification->occurredAt->toIso8601String() === '2026-10-05T10:00:00+02:00');
});

it('rejects a registration without options, with expired options or with options already used', function (string $case) {
    fakePasskeys();
    actingAsVerifiedCustomer();
    confirmIdentity();

    if ($case !== 'without options') {
        fromFrontend()->postJson('/api/v1/me/passkeys/options')->assertOk();
    }

    if ($case === 'expired options') {
        $this->travel(301)->seconds();
        confirmIdentity();
    }

    if ($case === 'options already used') {
        fromFrontend()->postJson('/api/v1/me/passkeys', ['name' => 'MacBook di Giulia', 'credential' => passkeyRegistrationAnswer()])->assertCreated();
    }

    fromFrontend()->postJson('/api/v1/me/passkeys', ['name' => 'iPhone di Giulia', 'credential' => passkeyRegistrationAnswer('YWx0cmEtcGFzc2tleQ')])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'La registrazione della passkey è scaduta: riprova.',
            'errors' => ['credential' => ['La registrazione della passkey è scaduta: riprova.']],
        ]);

    expect(Passkey::query()->where('credential_id', 'YWx0cmEtcGFzc2tleQ')->exists())->toBeFalse()
        ->and(SecurityEvent::query()->where('metadata->passkey_name', 'iPhone di Giulia')->exists())->toBeFalse();
})->with([
    'without options',
    'expired options',
    'options already used',
]);

it('accepts the registration options for 300 seconds', function () {
    fakePasskeys();
    actingAsVerifiedCustomer();
    confirmIdentity();
    fromFrontend()->postJson('/api/v1/me/passkeys/options')->assertOk();
    $this->travel(300)->seconds();

    fromFrontend()->postJson('/api/v1/me/passkeys', ['name' => 'iPhone di Giulia', 'credential' => passkeyRegistrationAnswer()])
        ->assertCreated();
});

it('rejects an answer that the library does not accept, and consumes the options', function () {
    fakePasskeys();
    $user = actingAsVerifiedCustomer();
    confirmIdentity();
    fromFrontend()->postJson('/api/v1/me/passkeys/options')->assertOk();

    fromFrontend()->postJson('/api/v1/me/passkeys', ['name' => 'iPhone di Giulia', 'credential' => passkeyRegistrationAnswer(valid: false)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.credential.0', 'Non è stato possibile registrare la passkey: riprova.');

    fromFrontend()->postJson('/api/v1/me/passkeys', ['name' => 'iPhone di Giulia', 'credential' => passkeyRegistrationAnswer()])
        ->assertUnprocessable()
        ->assertJsonPath('errors.credential.0', 'La registrazione della passkey è scaduta: riprova.');

    expect(Passkey::count())->toBe(0)
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSentTo($user, PasskeyAddedNotification::class);
});

it('validates the registration fields', function (array $data, string $field) {
    actingAsVerifiedCustomer();
    confirmIdentity();

    fromFrontend()->postJson('/api/v1/me/passkeys', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing name' => [['credential' => passkeyRegistrationAnswer()], 'name'],
    'blank name' => [['name' => '   ', 'credential' => passkeyRegistrationAnswer()], 'name'],
    'name of 256 characters' => [['name' => str_repeat('a', 256), 'credential' => passkeyRegistrationAnswer()], 'name'],
    'missing answer' => [['name' => 'iPhone di Giulia'], 'credential'],
    'answer that is not an object' => [['name' => 'iPhone di Giulia', 'credential' => 'abc'], 'credential'],
]);

it('renames a passkey of the user without asking for the identity', function () {
    $user = actingAsVerifiedCustomer();
    $passkey = passkeyFor($user, 'MacBook');

    fromFrontend()->patchJson("/api/v1/me/passkeys/{$passkey->id}", ['name' => ' MacBook di Giulia '])
        ->assertOk()
        ->assertJsonPath('data.id', $passkey->id)
        ->assertJsonPath('data.name', 'MacBook di Giulia');

    expect($passkey->fresh()->name)->toBe('MacBook di Giulia')
        ->and(SecurityEvent::count())->toBe(0);
    Notification::assertNothingSent();
});

it('validates the new name', function (array $data) {
    $passkey = passkeyFor(actingAsVerifiedCustomer());

    fromFrontend()->patchJson("/api/v1/me/passkeys/{$passkey->id}", $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
})->with([
    'missing' => [[]],
    'blank' => [['name' => '  ']],
    '256 characters' => [['name' => str_repeat('a', 256)]],
]);

it('hides the passkeys of other users', function (string $method, array $data) {
    actingAsVerifiedCustomer();
    $other = Passkey::factory()->create(['name' => 'MacBook di Davide']);
    confirmIdentity();

    fromFrontend()->json($method, "/api/v1/me/passkeys/{$other->id}", $data)
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);

    expect($other->fresh()->name)->toBe('MacBook di Davide');
})->with([
    'rename' => ['PATCH', ['name' => 'Rubata']],
    'removal' => ['DELETE', []],
]);

it('answers 404 for a passkey that does not exist', function () {
    actingAsVerifiedCustomer();
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me/passkeys/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);
});

it('asks for the identity confirmation before the removal', function () {
    $user = actingAsCustomer(userWithTwoFactor());
    $passkey = passkeyFor($user);

    fromFrontend()->deleteJson("/api/v1/me/passkeys/{$passkey->id}")
        ->assertStatus(423)
        ->assertJsonPath('requires', ['password', 'second_factor']);

    expect($passkey->fresh())->not->toBeNull();
});

it('checks the owner before asking for the identity confirmation', function () {
    actingAsVerifiedCustomer();
    $other = Passkey::factory()->create();

    fromFrontend()->deleteJson("/api/v1/me/passkeys/{$other->id}")
        ->assertNotFound();
});

it('removes a passkey with its event and notice', function () {
    $user = actingAsVerifiedCustomer();
    $passkey = passkeyFor($user, 'MacBook di Giulia');
    $kept = passkeyFor($user, 'iPhone di Giulia', 'aXBob25lLWdpdWxpYQ');
    confirmIdentity();

    fromFrontend()->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0')
        ->deleteJson("/api/v1/me/passkeys/{$passkey->id}")
        ->assertNoContent();

    $event = SecurityEvent::sole();

    expect(Passkey::query()->pluck('id')->all())->toBe([$kept->id])
        ->and($event->type)->toBe(SecurityEventType::PasskeyRemoved)
        ->and($event->metadata)->toBe(['passkey_name' => 'MacBook di Giulia']);

    Notification::assertSentTo($user, PasskeyRemovedNotification::class, fn (PasskeyRemovedNotification $notification) => $notification->passkeyName === 'MacBook di Giulia'
        && $notification->device === 'Firefox su Windows');
});

it('shows the passkey events in the recent activity', function () {
    fakePasskeys();
    actingAsVerifiedCustomer();
    confirmIdentity();
    fromFrontend()->postJson('/api/v1/me/passkeys/options')->assertOk();
    $id = fromFrontend()->postJson('/api/v1/me/passkeys', ['name' => 'iPhone di Giulia', 'credential' => passkeyRegistrationAnswer()])->json('data.id');
    $this->travel(1)->minutes();
    confirmIdentity();
    fromFrontend()->deleteJson("/api/v1/me/passkeys/{$id}")->assertNoContent();

    fromFrontend()->getJson('/api/v1/me/security-events')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'passkey_removed')
        ->assertJsonPath('data.0.label', 'Passkey rimossa')
        ->assertJsonPath('data.0.passkey_name', 'iPhone di Giulia')
        ->assertJsonPath('data.1.type', 'passkey_added')
        ->assertJsonPath('data.1.label', 'Passkey aggiunta')
        ->assertJsonPath('data.1.passkey_name', 'iPhone di Giulia');
});
