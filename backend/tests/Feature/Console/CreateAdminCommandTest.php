<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use Symfony\Component\Console\Exception\InvalidOptionException;

beforeEach(function () {
    Notification::fake();
});

function createAdmin(array $answers = []): PendingCommand
{
    $answers = [
        'name' => '  Marta Bianchi ',
        'email' => ' Marta.Bianchi@Esempio.it ',
        'password' => 'Bottega2026',
        'password_confirmation' => 'Bottega2026',
        ...$answers,
    ];

    return test()->artisan('admin:create')
        ->expectsQuestion('Nome e cognome', $answers['name'])
        ->expectsQuestion('Email', $answers['email'])
        ->expectsQuestion('Password (almeno 10 caratteri, con maiuscole, minuscole e numeri)', $answers['password'])
        ->expectsQuestion('Conferma la password', $answers['password_confirmation']);
}

it('creates a verified admin who can log in', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-12 10:30:00', 'Europe/Rome'));

    createAdmin()
        ->expectsOutputToContain('Amministratore creato: marta.bianchi@esempio.it')
        ->assertSuccessful();

    $admin = User::sole();

    expect($admin->name)->toBe('Marta Bianchi')
        ->and($admin->email)->toBe('marta.bianchi@esempio.it')
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and($admin->email_verified_at->toDateTimeString())->toBe('2026-10-12 10:30:00')
        ->and($admin->password_changed_at->toDateTimeString())->toBe('2026-10-12 10:30:00');

    Notification::assertNothingSent();

    fromFrontend()->postJson('/api/v1/login', ['email' => 'marta.bianchi@esempio.it', 'password' => 'Bottega2026'])
        ->assertOk()
        ->assertJsonPath('data.email', 'marta.bianchi@esempio.it')
        ->assertJsonPath('data.role.value', 'admin');
});

it('never prints the password', function () {
    createAdmin()
        ->doesntExpectOutputToContain('Bottega2026')
        ->assertSuccessful();
});

it('refuses an email already in use without promoting the account', function () {
    $customer = User::factory()->customer()->create(['email' => 'marta.bianchi@esempio.it']);

    createAdmin()
        ->expectsOutputToContain('Amministratore non creato.')
        ->expectsOutputToContain('Questo indirizzo email è già in uso.')
        ->assertFailed();

    expect(User::count())->toBe(1)
        ->and($customer->fresh()->role)->toBe(UserRole::Customer);
});

it('refuses an address with the reserved .invalid domain', function () {
    createAdmin(['email' => 'marta@esempio.invalid'])
        ->expectsOutputToContain('Inserisci un indirizzo email valido.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('refuses a weak password without printing it', function (string $password, string $message) {
    createAdmin(['password' => $password, 'password_confirmation' => $password])
        ->expectsOutputToContain($message)
        ->doesntExpectOutputToContain($password)
        ->assertFailed();

    expect(User::count())->toBe(0);
})->with([
    'of 9 characters' => ['Bottega26', 'Il campo password deve contenere almeno 10 caratteri.'],
    'without upper case' => ['bottega2026', 'Il campo password deve contenere almeno una lettera maiuscola e una minuscola.'],
    'without numbers' => ['BottegaArte', 'Il campo password deve contenere almeno un numero.'],
]);

it('refuses a confirmation that does not match', function () {
    createAdmin(['password_confirmation' => 'Bottega2027'])
        ->expectsOutputToContain('La conferma del campo password non corrisponde.')
        ->doesntExpectOutputToContain('Bottega2027')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('refuses empty answers', function () {
    createAdmin(['name' => '', 'email' => '', 'password' => '', 'password_confirmation' => ''])
        ->expectsOutputToContain('Il campo nome è obbligatorio.')
        ->expectsOutputToContain('Il campo email è obbligatorio.')
        ->expectsOutputToContain('Il campo password è obbligatorio.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('accepts no password as an option', function () {
    expect(fn () => $this->artisan('admin:create', ['--password' => 'Bottega2026']))
        ->toThrow(InvalidOptionException::class);
});
