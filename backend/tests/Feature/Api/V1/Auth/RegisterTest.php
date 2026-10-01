<?php

use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function registrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lucia Rinaldi',
        'email' => 'lucia.rinaldi@esempio.it',
        'password' => 'Montagna2026',
        'password_confirmation' => 'Montagna2026',
    ], $overrides);
}

beforeEach(function () {
    Notification::fake();
});

it('registers a customer and starts the session', function () {
    $this->travelTo('2026-10-01 10:30:00');

    fromFrontend()->postJson('/api/v1/register', registrationData())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'email_verified_at', 'role' => ['value', 'label'], 'pending_email_change', 'created_at']])
        ->assertJsonPath('data.name', 'Lucia Rinaldi')
        ->assertJsonPath('data.email', 'lucia.rinaldi@esempio.it')
        ->assertJsonPath('data.email_verified_at', null)
        ->assertJsonPath('data.role.value', 'customer')
        ->assertJsonPath('data.role.label', 'Cliente')
        ->assertJsonPath('data.pending_email_change', null)
        ->assertJsonMissingPath('data.password');

    $user = User::sole();

    expect($user->password_changed_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and($user->email_verified_at)->toBeNull();

    $this->assertAuthenticatedAs($user, 'web');
});

it('sends the verification email', function () {
    fromFrontend()->postJson('/api/v1/register', registrationData())->assertCreated();

    Notification::assertSentTo(User::sole(), VerifyEmailNotification::class);
});

it('writes no security event', function () {
    fromFrontend()->postJson('/api/v1/register', registrationData())->assertCreated();

    expect(SecurityEvent::count())->toBe(0);
});

it('stores the email in lower case without spaces', function () {
    fromFrontend()->postJson('/api/v1/register', registrationData(['email' => '  Lucia.Rinaldi@Esempio.IT ']))
        ->assertCreated()
        ->assertJsonPath('data.email', 'lucia.rinaldi@esempio.it');
});

it('creates a customer even when a role is sent', function () {
    fromFrontend()->postJson('/api/v1/register', registrationData(['role' => 'admin']))
        ->assertCreated()
        ->assertJsonPath('data.role.value', 'customer');
});

it('rejects invalid data', function (array $overrides, string $field) {
    fromFrontend()->postJson('/api/v1/register', registrationData($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(User::count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'name of 256 characters' => [['name' => str_repeat('a', 256)], 'name'],
    'missing email' => [['email' => ''], 'email'],
    'malformed email' => [['email' => 'lucia.esempio.it'], 'email'],
    'password of 9 characters' => [['password' => 'Montagna2', 'password_confirmation' => 'Montagna2'], 'password'],
    'password without upper case' => [['password' => 'montagna2026', 'password_confirmation' => 'montagna2026'], 'password'],
    'password without lower case' => [['password' => 'MONTAGNA2026', 'password_confirmation' => 'MONTAGNA2026'], 'password'],
    'password without numbers' => [['password' => 'MontagnaAlta', 'password_confirmation' => 'MontagnaAlta'], 'password'],
    'different confirmation' => [['password_confirmation' => 'Montagna2027'], 'password'],
]);

it('says that the email is already in use', function () {
    User::factory()->create(['email' => 'lucia.rinaldi@esempio.it']);

    fromFrontend()->postJson('/api/v1/register', registrationData(['email' => 'Lucia.Rinaldi@esempio.it']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Questo indirizzo email è già in uso.');
});

it('answers 422 when a simultaneous registration takes the email first', function () {
    User::creating(function () {
        DB::table('users')->insert([
            'name' => 'Lucia Rinaldi',
            'email' => 'lucia.rinaldi@esempio.it',
            'password' => 'hash',
            'role' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    fromFrontend()->postJson('/api/v1/register', registrationData())
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Questo indirizzo email è già in uso.');

    $this->assertGuest('web');
});

it('rejects addresses with the reserved .invalid domain', function (string $email) {
    fromFrontend()->postJson('/api/v1/register', registrationData(['email' => $email]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Inserisci un indirizzo email valido.');

    expect(User::count())->toBe(0);
})->with([
    'mario@esempio.invalid',
    'deleted-7@anonymized.invalid',
    'Mario@Posta.INVALID',
]);

it('accepts an address that only contains invalid before the domain', function () {
    fromFrontend()->postJson('/api/v1/register', registrationData(['email' => 'invalid@esempio.it']))
        ->assertCreated();
});

it('limits the registrations to 5 per hour for each ip', function () {
    $this->freezeTime();

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/register', registrationData(['email' => "cliente{$attempt}@esempio.it"]))
            ->assertCreated();
        $this->app['auth']->forgetGuards();
    }

    fromFrontend()->postJson('/api/v1/register', registrationData(['email' => 'cliente6@esempio.it']))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '3600')
        ->assertJsonPath('message', 'Troppi tentativi. Riprova tra 3600 secondi.');

    expect(User::count())->toBe(5);
});
