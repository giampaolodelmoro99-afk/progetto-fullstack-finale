<?php

namespace Database\Factories;

use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Pending by default. Only the SHA-256 hashes of the tokens are stored.
 *
 * @extends Factory<EmailChangeRequest>
 */
class EmailChangeRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'new_email' => fake()->unique()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'cancel_token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addMinutes(config('security.email_change_ttl')),
            'confirmed_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled_at' => now(),
        ]);
    }
}
