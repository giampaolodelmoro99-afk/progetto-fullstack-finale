<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Fixed demo accounts documented in the README (admin, demo customer,
 * customer with two-factor authentication, unverified customer), random
 * customers and a customer who deleted the account: names from Faker with a
 * fixed seed, dates from a Randomizer with its own seed. Everyone except the
 * deleted account shares the demo password.
 */
class UserSeeder extends Seeder
{
    public const PASSWORD = 'Silicio2026';

    /**
     * Demo only: documented in the README so the account can be added to an
     * authenticator app during the presentation.
     */
    public const TOTP_SECRET = 'CASADELSILICIODEMOTOTPSEGRETO234';

    public const RECOVERY_CODES = [
        'K7PM-2XQA',
        'R4TD-9WNE',
        'B8HV-3LCJ',
        'M2QF-7YRS',
        'T6ZN-4GKP',
        'W9CX-5DHB',
        'E3JL-8VMT',
        'P5RY-6NAF',
    ];

    private const FAKER_SEED = 2026;

    private const RANDOM_SEED = 2026;

    private const RANDOM_CUSTOMERS = 20;

    private const EMAIL_DOMAIN = 'esempio.it';

    public function run(): void
    {
        fake()->seed(self::FAKER_SEED);
        fake()->unique(true);

        $password = Hash::make(self::PASSWORD);
        $since = today()->subMonths(8)->setTime(9, 0);

        User::factory()->admin()->create([
            'name' => 'Marta Lenzi',
            'email' => 'marta.lenzi@'.self::EMAIL_DOMAIN,
            'password' => $password,
            'email_verified_at' => $since,
            'password_changed_at' => $since,
            'created_at' => $since,
            'updated_at' => $since,
        ]);

        User::factory()->customer()->create([
            'name' => 'Giulia Ferri',
            'email' => 'giulia.ferri@'.self::EMAIL_DOMAIN,
            'password' => $password,
            'email_verified_at' => $since,
            'password_changed_at' => $since,
            'created_at' => $since,
            'updated_at' => $since,
        ]);

        User::factory()->customer()->withTwoFactor()->create([
            'name' => 'Davide Conti',
            'email' => 'davide.conti@'.self::EMAIL_DOMAIN,
            'password' => $password,
            'email_verified_at' => $since,
            'password_changed_at' => $since,
            'two_factor_secret' => self::TOTP_SECRET,
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), self::RECOVERY_CODES),
            'two_factor_confirmed_at' => today()->subMonths(2)->setTime(21, 40),
            'created_at' => $since,
            'updated_at' => $since,
        ]);

        User::factory()->customer()->unverified()->create([
            'name' => 'Paolo Greco',
            'email' => 'paolo.greco@'.self::EMAIL_DOMAIN,
            'password' => $password,
            'password_changed_at' => today()->subDays(2)->setTime(19, 5),
            'created_at' => today()->subDays(2)->setTime(19, 5),
            'updated_at' => today()->subDays(2)->setTime(19, 5),
        ]);

        $this->seedRandomCustomers($password);
        $this->seedAnonymizedCustomer();
    }

    /**
     * A customer who deleted the account, as DeleteAccountAction leaves it:
     * fictitious .invalid address, random password, no personal data. Its
     * delivered order (OrderSeeder) shows «Cliente eliminato» in the admin
     * area. Seeded last, so the Faker draws of the factory do not change the
     * names of the random customers.
     */
    private function seedAnonymizedCustomer(): void
    {
        $createdAt = today()->subMonths(5)->setTime(10, 15);
        $deletedAt = today()->subDays(30)->setTime(20, 10);

        $user = User::factory()->customer()->create([
            'name' => User::ANONYMIZED_NAME,
            'email' => 'anonymized-placeholder@'.self::EMAIL_DOMAIN,
            'password' => Str::random(64),
            'password_changed_at' => $createdAt,
            'remember_token' => null,
            'created_at' => $createdAt,
            'updated_at' => $deletedAt,
        ]);

        $user->forceFill([
            'email' => $user->anonymizedEmail(),
            'email_verified_at' => null,
            'anonymized_at' => $deletedAt,
        ])->save();
    }

    private function seedRandomCustomers(string $password): void
    {
        $random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $usedEmails = User::pluck('email')->all();

        for ($i = 0; $i < self::RANDOM_CUSTOMERS; $i++) {
            $firstName = fake()->firstName();
            $lastName = fake()->lastName();
            $email = $this->uniqueEmail($firstName, $lastName, $usedEmails);
            $usedEmails[] = $email;
            $createdAt = today()->subMonths(7)->subDays($random->getInt(0, 60))->setTime($random->getInt(8, 22), $random->getInt(0, 59));

            User::factory()->customer()->create([
                'name' => "{$firstName} {$lastName}",
                'email' => $email,
                'password' => $password,
                'email_verified_at' => $createdAt,
                'password_changed_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    /**
     * nome.cognome@esempio.it in plain ASCII, with a numeric suffix for homonyms.
     *
     * @param  list<string>  $usedEmails
     */
    private function uniqueEmail(string $firstName, string $lastName, array $usedEmails): string
    {
        $local = Str::slug($firstName, '').'.'.Str::slug($lastName, '');
        $email = "{$local}@".self::EMAIL_DOMAIN;
        $counter = 1;

        while (in_array($email, $usedEmails, true)) {
            $counter++;
            $email = "{$local}{$counter}@".self::EMAIL_DOMAIN;
        }

        return $email;
    }
}
