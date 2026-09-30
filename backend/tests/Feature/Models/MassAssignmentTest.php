<?php

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

function discardingAttributesSilently(Closure $callback): mixed
{
    $previous = Model::preventsSilentlyDiscardingAttributes();
    Model::preventSilentlyDiscardingAttributes(false);

    try {
        return $callback();
    } finally {
        Model::preventSilentlyDiscardingAttributes($previous);
    }
}

it('refuses a mass assigned role outside production', function () {
    User::create([
        'name' => 'Giulia Ferri',
        'email' => 'giulia@example.com',
        'password' => 'Password1234',
        'role' => 'admin',
    ]);
})->throws(MassAssignmentException::class);

it('does not let mass assignment change the role of a user', function () {
    $user = discardingAttributesSilently(fn () => User::create([
        'name' => 'Giulia Ferri',
        'email' => 'giulia@example.com',
        'password' => 'Password1234',
        'role' => 'admin',
    ]));

    expect($user->fresh()->role)->toBe(UserRole::Customer);

    discardingAttributesSilently(fn () => $user->update(['role' => 'admin']));

    expect($user->fresh()->role)->toBe(UserRole::Customer);
});

it('does not let mass assignment set the two-factor columns', function () {
    $user = User::factory()->create();

    discardingAttributesSilently(fn () => $user->update([
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_recovery_codes' => ['code'],
        'two_factor_confirmed_at' => now(),
        'two_factor_last_used_step' => 1,
    ]));

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_last_used_step)->toBeNull();
});

it('always saves addresses in italy', function () {
    $data = Address::factory()->make()->only(['full_name', 'street', 'city', 'postal_code', 'province', 'phone']);
    $user = User::factory()->create();

    $saved = discardingAttributesSilently(fn () => $user->addresses()->create([...$data, 'country' => 'FR']));

    expect($saved->fresh()->country)->toBe('IT');
});

it('creates a new product as not visible', function () {
    $product = Category::factory()->create()->products()->create([
        'name' => 'Orologio Meridiana',
        'sku' => 'CDS-IND-001',
        'short_description' => 'Un orologio essenziale.',
        'specifications' => [],
        'price' => 19900,
    ]);

    $product->refresh();

    expect($product->is_active)->toBeFalse()
        ->and($product->stock)->toBe(0);
});

it('hides the password and the two-factor secrets from serialization', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
});

it('stores the two-factor secrets encrypted', function () {
    $user = User::factory()->withTwoFactor()->create();

    $raw = DB::table('users')->where('id', $user->id)->first();

    expect($raw->two_factor_secret)->not->toBe($user->two_factor_secret)
        ->and(decrypt($raw->two_factor_secret, false))->toBe($user->two_factor_secret)
        ->and($raw->two_factor_recovery_codes)->not->toContain('$2y$');
});

it('makes a product specification list round-trip as an ordered array', function () {
    $specifications = [
        ['label' => 'Dimensioni', 'value' => '18 × 18 × 34 cm'],
        ['label' => 'Peso', 'value' => '4,2 kg'],
        ['label' => 'Potenza', 'value' => '2 × 40 W'],
    ];

    $product = Product::factory()->create(['specifications' => $specifications]);

    expect($product->fresh()->specifications)->toBe($specifications);
});
