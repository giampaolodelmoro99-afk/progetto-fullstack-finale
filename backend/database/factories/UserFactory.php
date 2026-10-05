<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\EmailChangeRequest;
use App\Models\Passkey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Plain recovery codes stored (hashed) by withTwoFactor().
     */
    public const RECOVERY_CODES = [
        'AB12-CD34',
        'EF56-GH78',
        'JK90-LM12',
        'NP34-QR56',
        'ST78-UV90',
        'WX12-YZ34',
        'BC56-DF78',
        'GH90-JK12',
    ];

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @var list<string>|null
     */
    protected static ?array $hashedRecoveryCodes = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Customer,
            'password_changed_at' => now(),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Customer,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Confirmed TOTP with a random secret and the RECOVERY_CODES, stored as bcrypt hashes.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => self::base32Secret(),
            'two_factor_recovery_codes' => static::$hashedRecoveryCodes ??= array_map(
                fn (string $code): string => Hash::make($code),
                self::RECOVERY_CODES,
            ),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * A fake passkey with the given name, without two-factor authentication.
     */
    public function withPasskey(string $name = 'MacBook di prova'): static
    {
        return $this->afterCreating(function (User $user) use ($name) {
            Passkey::factory()->for($user)->create(['name' => $name]);
        });
    }

    /**
     * An email change request that is not confirmed, not cancelled and not expired.
     */
    public function withPendingEmailChange(string $newEmail): static
    {
        return $this->afterCreating(function (User $user) use ($newEmail) {
            EmailChangeRequest::factory()->for($user)->create(['new_email' => $newEmail]);
        });
    }

    private static function base32Secret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }

        return $secret;
    }
}
