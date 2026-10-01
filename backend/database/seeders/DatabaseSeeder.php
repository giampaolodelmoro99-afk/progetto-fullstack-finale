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
     *
     * Why the seeders draw with their own Randomizer: Faker::seed() seeds the
     * global mt_rand of PHP, and Faker\Generator::__destruct() calls seed()
     * without a value, that is mt_srand() with a random seed. A generator of
     * an older container (another test, another seeding) is destroyed by the
     * cycle collector of PHP at an unpredictable moment; if that happens in
     * the middle of the seeding, every following mt_rand draw changes and the
     * demo data is no longer the same. So:
     * - the draws decided by the seeders (days, hours, customers, states,
     *   order lines) use a Random\Randomizer with a Mt19937 engine and its
     *   own seed, whose state no mt_srand() can touch;
     * - gc_collect_cycles() runs first, so the old generators are destroyed
     *   before the seeds are set, which also protects the names and the
     *   addresses that Faker still generates.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo data cannot be seeded in production: the demo accounts have a public password.');
        }

        gc_collect_cycles();

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
