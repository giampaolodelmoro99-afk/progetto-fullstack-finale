<?php

use App\Enums\UserRole;
use App\Models\Passkey;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\AdminRemovedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(Carbon\Carbon::parse('2026-10-13 09:15:00', 'Europe/Rome'));

    $this->remaining = User::factory()->admin()->create(['name' => 'Marta Lenzi', 'email' => 'marta.lenzi@esempio.it']);
});

function adminToRemove(): User
{
    return User::factory()->admin()->create([
        'name' => 'Paolo Neri',
        'email' => 'paolo.neri@esempio.it',
        'password' => 'Bottega2026',
    ]);
}

function removeAdmin(string $email, ?string $confirmation = null): PendingCommand
{
    $command = test()->artisan('admin:remove')->expectsQuestion('Email', $email);

    if ($confirmation !== null) {
        $command->expectsQuestion('Riscrivi l\'email per confermare la rimozione', $confirmation);
    }

    return $command;
}

it('removes an admin by anonymizing it and refuses the login afterwards', function () {
    $admin = adminToRemove();
    Passkey::factory()->for($admin)->create();
    SecurityEvent::factory()->for($admin)->create();
    insertSession('admin-session', $admin);

    removeAdmin(' Paolo.Neri@Esempio.it ', 'PAOLO.NERI@esempio.it ')
        ->expectsOutputToContain('Amministratore trovato: Paolo Neri <paolo.neri@esempio.it>')
        ->expectsOutputToContain('Amministratore rimosso: paolo.neri@esempio.it')
        ->assertSuccessful();

    $admin->refresh();

    expect($admin->name)->toBe('Utente eliminato')
        ->and($admin->email)->toBe("deleted-{$admin->id}@anonymized.invalid")
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and($admin->anonymized_at->toDateTimeString())->toBe('2026-10-13 09:15:00')
        ->and($admin->passkeys()->count())->toBe(0)
        ->and($admin->securityEvents()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $admin->id)->exists())->toBeFalse()
        ->and($this->remaining->fresh()->isAnonymized())->toBeFalse();

    Notification::assertSentOnDemand(
        AdminRemovedNotification::class,
        fn (AdminRemovedNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'paolo.neri@esempio.it'
            && $notification->name === 'Paolo Neri',
    );
    Notification::assertSentTimes(AdminRemovedNotification::class, 1);

    fromFrontend()->postJson('/api/v1/login', ['email' => 'paolo.neri@esempio.it', 'password' => 'Bottega2026'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_credentials');
});

it('changes nothing when the confirmation does not match', function () {
    $admin = adminToRemove();

    removeAdmin('paolo.neri@esempio.it', 'paolo.nero@esempio.it')
        ->expectsOutputToContain('Conferma non corrispondente: nessuna modifica.')
        ->doesntExpectOutputToContain('Amministratore rimosso')
        ->assertFailed();

    expect($admin->fresh()->isAnonymized())->toBeFalse()
        ->and($admin->fresh()->email)->toBe('paolo.neri@esempio.it');
    Notification::assertNothingSent();
});

it('refuses an email that belongs to no account', function () {
    $admin = adminToRemove();

    removeAdmin('nessuno@esempio.it')
        ->expectsOutputToContain('Nessun account con questa email.')
        ->assertFailed();

    expect($admin->fresh()->isAnonymized())->toBeFalse();
    Notification::assertNothingSent();
});

it('refuses an empty answer', function () {
    removeAdmin('')
        ->expectsOutputToContain('Nessun account con questa email.')
        ->assertFailed();

    Notification::assertNothingSent();
});

it('refuses the email of a customer without touching the account', function () {
    adminToRemove();
    $customer = User::factory()->customer()->create(['name' => 'Giulia Ferri', 'email' => 'giulia.ferri@esempio.it']);

    removeAdmin('giulia.ferri@esempio.it')
        ->expectsOutputToContain('Questo account è un cliente: il comando rimuove solo amministratori.')
        ->doesntExpectOutputToContain('Giulia Ferri')
        ->assertFailed();

    expect($customer->fresh()->isAnonymized())->toBeFalse()
        ->and($customer->fresh()->name)->toBe('Giulia Ferri');
    Notification::assertNothingSent();
});

it('refuses the removal when the other admin is removed between the check and the lock', function () {
    $admin = adminToRemove();
    $raced = false;

    DB::beforeExecuting(function (string $query) use (&$raced) {
        if ($raced || ! str_contains($query, 'for update')) {
            return;
        }

        $raced = true;
        DB::table('users')->where('email', 'marta.lenzi@esempio.it')->update(['anonymized_at' => now()]);
    });

    removeAdmin('paolo.neri@esempio.it', 'paolo.neri@esempio.it')
        ->expectsOutputToContain('Amministratore non rimosso. Non puoi rimuovere l\'ultimo amministratore attivo.')
        ->doesntExpectOutputToContain('Amministratore rimosso:')
        ->assertFailed();

    expect($raced)->toBeTrue()
        ->and($admin->fresh()->isAnonymized())->toBeFalse()
        ->and($admin->fresh()->email)->toBe('paolo.neri@esempio.it');
    Notification::assertNothingSent();
});

it('refuses the last active admin, an anonymized admin not counting', function () {
    User::factory()->admin()->anonymized()->create();

    removeAdmin('marta.lenzi@esempio.it')
        ->expectsOutputToContain('Non puoi rimuovere l\'ultimo amministratore attivo.')
        ->assertFailed();

    expect($this->remaining->fresh()->isAnonymized())->toBeFalse();
    Notification::assertNothingSent();
});
