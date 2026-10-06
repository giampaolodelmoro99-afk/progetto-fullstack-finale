<?php

use App\Contracts\Security\PasskeyCeremony;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Passkey;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\FakePasskeyCeremony;
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

/**
 * Slugs of the products in the data of a catalog response, in order.
 */
function responseSlugs(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('slug')->all();
}

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
 * Marks the identity as confirmed now in the session of the next requests.
 */
function confirmIdentity(): TestCase
{
    return test()->withSession(['auth.identity_confirmed_at' => now()->getTimestamp()]);
}

/**
 * Fixed TOTP secret of the tests: with a fixed time every code is
 * deterministic, so a wrong code such as 000000 never matches by chance.
 */
function totpSecret(): string
{
    return 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
}

/**
 * A user with confirmed two-factor authentication on the fixed secret and
 * the recovery codes of UserFactory::RECOVERY_CODES.
 */
function userWithTwoFactor(array $attributes = []): User
{
    return User::factory()->withTwoFactor()->create(['two_factor_secret' => totpSecret(), ...$attributes]);
}

/**
 * A code of the authenticator app for the 30 second interval of now(),
 * moved by $offset intervals, generated with the library of the code.
 */
function totpCode(string $secret, int $offset = 0): string
{
    return app(Google2FA::class)->oathTotp($secret, intdiv(now()->getTimestamp(), 30) + $offset);
}

/**
 * Replaces the WebAuthn cryptography with FakePasskeyCeremony; the options
 * kept in the session stay real.
 */
function fakePasskeys(): void
{
    app()->instance(PasskeyCeremony::class, new FakePasskeyCeremony);
}

/**
 * A passkey of the user, with a credential id that the tests can name.
 */
function passkeyFor(User $user, string $name = 'MacBook di Giulia', string $credentialId = 'Y3JlZGVudGlhbC1naXVsaWE'): Passkey
{
    return Passkey::factory()->for($user)->create(['name' => $name, 'credential_id' => $credentialId]);
}

/**
 * Answer of the browser to verification options, as FakePasskeyCeremony
 * reads it: valid unless the signature is "invalid".
 *
 * @return array<string, mixed>
 */
function passkeyAnswer(string $credentialId = 'Y3JlZGVudGlhbC1naXVsaWE', bool $valid = true): array
{
    return [
        'id' => $credentialId,
        'rawId' => $credentialId,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => 'eyJ0eXBlIjoid2ViYXV0aG4uZ2V0In0',
            'authenticatorData' => 'SZYN5YgOjGh0NBcPZHZgW4_krrmihjLHmVzzuoMdl2M',
            'signature' => $valid ? 'MEUCIQDvalidsignature' : 'invalid',
        ],
        'clientExtensionResults' => [],
    ];
}

/**
 * Answer of the browser to registration options, as FakePasskeyCeremony
 * reads it: valid unless the attestation is "invalid".
 *
 * @return array<string, mixed>
 */
function passkeyRegistrationAnswer(string $credentialId = 'bnVvdmEtcGFzc2tleQ', bool $valid = true): array
{
    return [
        'id' => $credentialId,
        'rawId' => $credentialId,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => 'eyJ0eXBlIjoid2ViYXV0aG4uY3JlYXRlIn0',
            'attestationObject' => $valid ? 'o2NmbXRkbm9uZQ' : 'invalid',
        ],
        'clientExtensionResults' => [],
    ];
}

/**
 * A row of the database session driver for the user, as another browser
 * would leave it.
 */
function insertSession(string $id, User $user): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0',
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);
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

/**
 * Sends a conversation to the assistant endpoint. A single string is one
 * user message.
 */
function askAssistant(array|string $messages): TestResponse
{
    if (is_string($messages)) {
        $messages = [['role' => 'user', 'content' => $messages]];
    }

    return test()->postJson('/api/v1/assistant/messages', ['messages' => $messages]);
}

/**
 * The NDJSON events of a streamed assistant answer, decoded line by line.
 * streamedContent() runs the callback of the StreamedResponse: without it
 * nothing is streamed and the slot is never released.
 *
 * @return list<array<string, mixed>>
 */
function assistantEvents(TestResponse $response): array
{
    $lines = array_filter(explode("\n", $response->streamedContent()), fn (string $line): bool => $line !== '');

    return array_values(array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), $lines));
}

/**
 * A complete body of the admin product form, valid unless overridden.
 *
 * @return array<string, mixed>
 */
function adminProductPayload(array $overrides = []): array
{
    return [
        'category_id' => $overrides['category_id'] ?? Category::factory()->create()->id,
        'name' => 'Cuffie Aria',
        'sku' => 'CDS-AUD-001',
        'short_description' => 'Cuffie wireless con cancellazione del rumore.',
        'description' => 'Suono pulito e bilanciato.',
        'specifications' => [
            ['label' => 'Peso', 'value' => '250 g'],
            ['label' => 'Autonomia', 'value' => '30 ore'],
        ],
        'price' => 34900,
        'stock' => 18,
        'is_active' => true,
        ...$overrides,
    ];
}

/**
 * A product of the shop, active and with plenty of stock unless overridden.
 */
function shopProduct(array $attributes = []): Product
{
    return Product::factory()->create(['stock' => 20, ...$attributes]);
}

/**
 * A row in the cart of the user, creating the cart when it is missing.
 */
function cartRow(User $user, Product $product, int $quantity): CartItem
{
    $cart = $user->cart()->firstOrCreate();

    return CartItem::factory()->for($cart)->for($product)->create(['quantity' => $quantity]);
}

/**
 * A complete, valid body of the address form, unless overridden.
 *
 * @return array<string, mixed>
 */
function addressPayload(array $overrides = []): array
{
    return [
        'full_name' => 'Giulia Ferri',
        'street' => 'Via San Martino 42',
        'city' => 'Pisa',
        'postal_code' => '56125',
        'province' => 'PI',
        'phone' => '+39 333 123 4567',
        ...$overrides,
    ];
}

/**
 * Anonymizes the account in the database only, as a concurrent account
 * deletion does: the instance in memory still looks like an active user.
 */
function anonymizeBehindTheSession(User $user): void
{
    User::query()->whereKey($user->id)->update(['anonymized_at' => '2026-09-01 10:00:00']);
}
