<?php

use App\Actions\Account\DeleteAccountAction;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EmailChangeRequest;
use App\Models\Order;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01 10:30:00');
});

function customerToDelete(array $attributes = []): User
{
    return User::factory()->customer()->create([
        'name' => 'Giulia Ferri',
        'email' => 'giulia.ferri@esempio.it',
        ...$attributes,
    ]);
}

/**
 * Addresses, cart with a row, security event, email change request, reset
 * token and session of the user.
 */
function givePersonalData(User $user, string $sessionId): void
{
    Address::factory()->for($user)->count(2)->create();
    CartItem::factory()->for(Cart::factory()->for($user))->create();
    SecurityEvent::factory()->for($user)->create();
    EmailChangeRequest::factory()->for($user)->create();
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'hash', 'created_at' => now()]);
    insertSession($sessionId, $user);
}

/**
 * @return array<int, mixed>
 */
function ordersSnapshot(User $user): array
{
    return Order::with(['items', 'statusChanges', 'payments'])
        ->whereBelongsTo($user)
        ->orderBy('id')
        ->get()
        ->toArray();
}

it('rejects a guest', function () {
    fromFrontend()->deleteJson('/api/v1/me')
        ->assertUnauthorized();
});

it('asks a customer for the identity confirmation', function (bool $twoFactor, array $requires) {
    $user = actingAsCustomer($twoFactor ? userWithTwoFactor() : customerToDelete());

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertStatus(423)
        ->assertJsonPath('requires', $requires);

    expect($user->fresh()->anonymized_at)->toBeNull();
})->with([
    'without two-factor authentication' => [false, ['password']],
    'with two-factor authentication' => [true, ['password', 'second_factor']],
]);

it('forbids an administrator before asking for the identity confirmation', function (bool $confirmed) {
    $admin = actingAsAdmin();

    if ($confirmed) {
        confirmIdentity();
    }

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Gli account di amministrazione non si possono eliminare.']);

    expect($admin->fresh()->anonymized_at)->toBeNull()
        ->and($admin->fresh()->email)->toBe($admin->email);
    Notification::assertNothingSent();
})->with([
    'without confirmation' => false,
    'with confirmation' => true,
]);

it('deletes the account of a customer after the identity confirmation', function () {
    $user = actingAsCustomer(customerToDelete());
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    expect($user->fresh()->anonymized_at->toDateTimeString())->toBe('2026-10-01 10:30:00');
});

it('refuses with open orders and changes nothing', function (Closure $order) {
    $user = actingAsCustomer(customerToDelete());
    givePersonalData($user, 'other-browser');
    $order($user);
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertConflict()
        ->assertExactJson([
            'message' => 'Hai ordini ancora in corso: potrai eliminare l\'account quando saranno consegnati o annullati.',
            'code' => 'account_has_open_orders',
        ]);

    $user->refresh();

    expect($user->anonymized_at)->toBeNull()
        ->and($user->email)->toBe('giulia.ferri@esempio.it')
        ->and($user->name)->toBe('Giulia Ferri')
        ->and(Address::whereBelongsTo($user)->count())->toBe(2)
        ->and(Cart::whereBelongsTo($user)->count())->toBe(1)
        ->and(SecurityEvent::whereBelongsTo($user)->count())->toBe(1)
        ->and(EmailChangeRequest::whereBelongsTo($user)->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', 'other-browser')->exists())->toBeTrue();
    $this->assertDatabaseHas('password_reset_tokens', ['email' => 'giulia.ferri@esempio.it']);
    Notification::assertNothingSent();
})->with([
    'paid order' => [fn (User $user) => Order::factory()->for($user)->paid()->create()],
    'shipped order' => [fn (User $user) => Order::factory()->for($user)->shipped()->create()],
    'pending order created 14 minutes ago' => [fn (User $user) => Order::factory()->for($user)->pending()->create(['created_at' => now()->subMinutes(14)])],
]);

it('allows the deletion with closed orders only', function (Closure $orders) {
    $user = actingAsCustomer(customerToDelete());
    $orders($user);
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    expect($user->fresh()->anonymized_at)->not->toBeNull();
})->with([
    'pending order created 16 minutes ago' => [fn (User $user) => Order::factory()->for($user)->pending()->create(['created_at' => now()->subMinutes(16)])],
    'delivered, cancelled and failed orders' => [function (User $user) {
        Order::factory()->for($user)->delivered()->create();
        Order::factory()->for($user)->cancelledAfterPayment()->create();
        Order::factory()->for($user)->failedPayment()->create();
    }],
    'no orders' => [fn (User $user) => null],
]);

it('ignores the open orders of other customers', function () {
    $user = actingAsCustomer(customerToDelete());
    Order::factory()->paid()->create();
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();
});

it('erases the personal data of the user and keeps those of the others', function () {
    $user = actingAsCustomer(customerToDelete());
    $other = User::factory()->create(['email' => 'davide.conti@esempio.it']);
    givePersonalData($user, 'giulia-browser');
    givePersonalData($other, 'davide-browser');
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    expect(Address::whereBelongsTo($user)->count())->toBe(0)
        ->and(Cart::whereBelongsTo($user)->count())->toBe(0)
        ->and(CartItem::whereHas('cart', fn ($query) => $query->whereBelongsTo($user))->count())->toBe(0)
        ->and(SecurityEvent::whereBelongsTo($user)->count())->toBe(0)
        ->and(EmailChangeRequest::whereBelongsTo($user)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Address::whereBelongsTo($other)->count())->toBe(2)
        ->and(Cart::whereBelongsTo($other)->count())->toBe(1)
        ->and(CartItem::count())->toBe(1)
        ->and(SecurityEvent::whereBelongsTo($other)->count())->toBe(1)
        ->and(EmailChangeRequest::whereBelongsTo($other)->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', 'davide-browser')->exists())->toBeTrue();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'giulia.ferri@esempio.it']);
    $this->assertDatabaseHas('password_reset_tokens', ['email' => 'davide.conti@esempio.it']);
});

it('anonymizes the user', function () {
    $user = actingAsCustomer(customerToDelete(['remember_token' => 'token-di-sessione']));
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    $user->refresh();

    expect($user->name)->toBe('Utente eliminato')
        ->and($user->email)->toBe("deleted-{$user->id}@anonymized.invalid")
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->remember_token)->toBeNull()
        ->and($user->anonymized_at->toDateTimeString())->toBe('2026-10-01 10:30:00')
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->role->value)->toBe('customer');
});

it('turns off two-factor authentication', function () {
    $user = actingAsCustomer(userWithTwoFactor(['two_factor_last_used_step' => 59694779]));
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_last_used_step)->toBeNull();
});

it('keeps orders, items, address snapshots, history and payments', function () {
    $user = actingAsCustomer(customerToDelete());
    Order::factory()->for($user)->delivered()->create();
    Order::factory()->for($user)->cancelledAfterPayment($user)->create();
    Order::factory()->for($user)->failedPayment()->create();
    $before = ordersSnapshot($user);
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    expect(ordersSnapshot($user))->toBe($before)
        ->and($before)->toHaveCount(3);
});

it('writes no security event', function () {
    $user = actingAsCustomer(customerToDelete());
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    expect(SecurityEvent::count())->toBe(0);
});

it('sends the notice to the original address after the deletion', function () {
    actingAsCustomer(customerToDelete());
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    Notification::assertSentOnDemand(
        AccountDeletedNotification::class,
        fn (AccountDeletedNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'giulia.ferri@esempio.it'
            && $notification->name === 'Giulia Ferri'
            && $notification->occurredAt->toDateTimeString() === '2026-10-01 10:30:00'
            && $notification->contacts === [
                'Scrivici ad assistenza@casadelsilicio.example o chiamaci al +39 050 000 0000.',
                'Rispondiamo dal lunedì al venerdì, dalle 9:00 alle 18:00.',
            ],
    );
    Notification::assertSentTimes(AccountDeletedNotification::class, 1);
});

it('changes nothing and sends no notice when the transaction fails', function () {
    $user = actingAsCustomer(customerToDelete());
    givePersonalData($user, 'other-browser');
    confirmIdentity();
    User::saved(fn () => throw new RuntimeException('Anonymization failed'));

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertServerError();

    $user->refresh();

    expect($user->anonymized_at)->toBeNull()
        ->and($user->email)->toBe('giulia.ferri@esempio.it')
        ->and($user->name)->toBe('Giulia Ferri')
        ->and(Address::whereBelongsTo($user)->count())->toBe(2)
        ->and(SecurityEvent::whereBelongsTo($user)->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', 'other-browser')->exists())->toBeTrue();
    Notification::assertNothingSent();
});

it('closes the current session', function () {
    customerToDelete();
    loginThroughApi('giulia.ferri@esempio.it');

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])->assertOk();
    fromFrontend()->deleteJson('/api/v1/me')->assertNoContent();
    forgetGuards();

    fromFrontend()->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('refuses the login with the original email and the old password', function () {
    Sleep::fake();
    customerToDelete();
    loginThroughApi('giulia.ferri@esempio.it');

    fromFrontend()->postJson('/api/v1/me/confirm-identity', ['password' => 'password'])->assertOk();
    fromFrontend()->deleteJson('/api/v1/me')->assertNoContent();
    forgetGuards();

    fromFrontend()->postJson('/api/v1/login', ['email' => 'giulia.ferri@esempio.it', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'Credenziali non valide', 'code' => 'invalid_credentials']);
});

it('frees the original address for a new account without the old orders', function () {
    $user = actingAsCustomer(customerToDelete());
    Order::factory()->for($user)->delivered()->create();
    confirmIdentity();

    fromFrontend()->deleteJson('/api/v1/me')->assertNoContent();
    forgetGuards();

    fromFrontend()->postJson('/api/v1/register', [
        'name' => 'Giulia Ferri',
        'email' => 'giulia.ferri@esempio.it',
        'password' => 'Montagna2026',
        'password_confirmation' => 'Montagna2026',
    ])->assertCreated();

    $newUser = User::where('email', 'giulia.ferri@esempio.it')->sole();

    expect($newUser->id)->not->toBe($user->id)
        ->and($newUser->anonymized_at)->toBeNull()
        ->and(Order::whereBelongsTo($newUser)->count())->toBe(0)
        ->and(Order::whereBelongsTo($user)->count())->toBe(1);
});

it('does nothing on an account already anonymized', function () {
    $user = customerToDelete();
    $user->forceFill([
        'name' => 'Utente eliminato',
        'email' => "deleted-{$user->id}@anonymized.invalid",
        'anonymized_at' => '2026-09-01 10:00:00',
    ])->save();

    app(DeleteAccountAction::class)->execute($user);

    expect($user->fresh()->anonymized_at->toDateTimeString())->toBe('2026-09-01 10:00:00');
    Notification::assertNothingSent();
});

it('notifies the address read under the lock', function () {
    $user = actingAsCustomer(customerToDelete());
    confirmIdentity();
    DB::table('users')->where('id', $user->id)->update(['email' => 'giulia.nuova@esempio.it']);

    fromFrontend()->deleteJson('/api/v1/me')
        ->assertNoContent();

    Notification::assertSentOnDemand(
        AccountDeletedNotification::class,
        fn (AccountDeletedNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'giulia.nuova@esempio.it',
    );
});
