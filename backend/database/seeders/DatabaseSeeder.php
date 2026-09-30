<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the demo shop, in dependency order. Fixed data is written by hand,
     * the surrounding data uses Faker with fixed seeds: the same seeding date
     * always produces the same database. Never in production: the demo
     * accounts share a public password.
     *
     * Everything runs in one transaction, so an interrupted seeding leaves the
     * database empty and db:seed-if-empty runs it again on the next start. The
     * files copied to the public disk are not covered by the transaction: a
     * failed seeding may leave orphan images, which ProductSeeder removes on
     * the next attempt by emptying the folder before copying.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo data cannot be seeded in production: the demo accounts have a public password.');
        }

        DB::transaction(fn () => $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            UserSeeder::class,
            AddressSeeder::class,
            CartSeeder::class,
            OrderSeeder::class,
        ]));
    }
}
