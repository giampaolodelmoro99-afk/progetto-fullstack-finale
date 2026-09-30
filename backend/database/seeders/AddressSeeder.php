<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fixed addresses of the demo customers (Giulia's from the mockups) and one
 * default Faker address for every other verified customer. The unverified
 * customer has none: without verification there is no checkout. Addresses
 * are dated like their user, so they predate the orders that copy them.
 */
class AddressSeeder extends Seeder
{
    private const FAKER_SEED = 3030;

    public function run(): void
    {
        fake()->seed(self::FAKER_SEED);
        fake()->unique(true);

        $giulia = User::where('email', 'giulia.ferri@esempio.it')->firstOrFail();

        $this->addAddress($giulia, [
            'full_name' => 'Giulia Ferri',
            'street' => 'Via San Martino 42',
            'city' => 'Pisa',
            'postal_code' => '56125',
            'province' => 'PI',
            'phone' => '+39 333 123 4567',
            'is_default' => true,
        ]);

        $this->addAddress($giulia, [
            'full_name' => 'Giulia Ferri',
            'street' => 'Via dei Servi 15',
            'city' => 'Firenze',
            'postal_code' => '50122',
            'province' => 'FI',
            'phone' => '+39 333 123 4567',
            'is_default' => false,
        ]);

        $this->addAddress(User::where('email', 'davide.conti@esempio.it')->firstOrFail(), [
            'full_name' => 'Davide Conti',
            'street' => 'Via Po 12',
            'city' => 'Torino',
            'postal_code' => '10123',
            'province' => 'TO',
            'phone' => '+39 347 555 0192',
            'is_default' => true,
        ]);

        User::where('role', UserRole::Customer)
            ->whereNotNull('email_verified_at')
            ->whereDoesntHave('addresses')
            ->orderBy('id')
            ->get()
            ->each(fn (User $customer) => Address::factory()->for($customer)->asDefault()->create([
                'full_name' => $customer->name,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->created_at,
            ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addAddress(User $user, array $attributes): void
    {
        $user->addresses()->make($attributes)->forceFill([
            'created_at' => $user->created_at,
            'updated_at' => $user->created_at,
        ])->save();
    }
}
