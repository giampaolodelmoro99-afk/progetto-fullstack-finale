<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Seeders are called in dependency order as they are added:
     * UserSeeder, CategorySeeder, ProductSeeder, AddressSeeder, OrderSeeder.
     */
    public function run(): void
    {
        $this->call([]);
    }
}
