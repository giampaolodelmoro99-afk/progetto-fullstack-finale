<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every Feature test runs on the ecommerce_testing database, rebuilt by
| RefreshDatabase. Unit tests boot the application too (configuration and
| time travel), but never touch the database. Helpers such as
| actingAsCustomer() and actingAsAdmin() are added here with the endpoints.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function frontendOrigin(): string
{
    return 'http://localhost:5173';
}

/**
 * Sends the next requests as the SPA does: Sanctum recognises the origin
 * and starts the session, which login, logout and registration need.
 */
function fromFrontend(): TestCase
{
    return test()->withHeaders([
        'Origin' => frontendOrigin(),
        'Referer' => frontendOrigin().'/',
    ]);
}

/**
 * Logs in through the API and makes the next requests behave like separate
 * browser requests: they carry the session cookie and the guards forget
 * the user kept in memory, so only the session decides who is logged in.
 */
function loginThroughApi(string $email, string $password = 'password'): void
{
    fromFrontend()->postJson('/api/v1/login', ['email' => $email, 'password' => $password])->assertOk();

    test()->withCookie(config('session.cookie'), session()->getId());
    forgetGuards();
}

/**
 * Drops the guards and the user they keep in memory, as a new request does.
 */
function forgetGuards(): void
{
    app('auth')->forgetGuards();
}

/**
 * The acting helpers authenticate on the web guard, as a real login does,
 * so a logout or a closed session takes effect on the next request.
 * actingAsCustomer() uses a customer whose email is not verified yet.
 */
function actingAsCustomer(?User $user = null): User
{
    $user ??= User::factory()->customer()->unverified()->create();
    test()->actingAs($user, 'web');

    return $user;
}

function actingAsVerifiedCustomer(): User
{
    return actingAsCustomer(User::factory()->customer()->create());
}

function actingAsAdmin(): User
{
    $user = User::factory()->admin()->create();
    test()->actingAs($user, 'web');

    return $user;
}

/**
 * Iterates the events of an assistant answer. The events received before an
 * exception stay in $received.
 */
function drainAssistantEvents(iterable $events, array &$received = []): array
{
    foreach ($events as $event) {
        $received[] = $event;
    }

    return $received;
}
