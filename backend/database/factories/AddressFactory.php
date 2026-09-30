<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    private const PROVINCES = ['MI', 'RM', 'TO', 'NA', 'FI', 'PI', 'BO', 'VE', 'GE', 'BA', 'PA', 'CA', 'TN', 'PD', 'VR'];

    /**
     * Valid Italian address: 5-digit postal code, province code, phone with digits and spaces.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'street' => 'Via '.fake()->lastName().' '.fake()->numberBetween(1, 200),
            'city' => fake()->city(),
            'postal_code' => fake()->numerify('#####'),
            'province' => fake()->randomElement(self::PROVINCES),
            'country' => 'IT',
            'phone' => '+39 3'.fake()->numerify('## ### ####'),
            'is_default' => false,
        ];
    }

    public function asDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
