<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SeedIfEmptyCommand extends Command
{
    protected $signature = 'db:seed-if-empty {--class=Database\\Seeders\\DatabaseSeeder : The seeder to run}';

    protected $description = 'Seed the database only when the users table is empty';

    public function handle(): int
    {
        if (DB::table('users')->exists()) {
            $this->components->info('Database already populated, seeding skipped.');

            return self::SUCCESS;
        }

        $this->components->info('Empty database, running the seeders.');

        return $this->call('db:seed', [
            '--class' => $this->option('class'),
            '--force' => true,
        ]);
    }
}
