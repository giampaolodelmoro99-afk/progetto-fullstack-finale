<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Database\Seeder;

final class SeedIfEmptyProbeSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['email' => 'probe@example.com']);
    }
}

it('runs the seeders when the database is empty', function () {
    $this->artisan('db:seed-if-empty', ['--class' => SeedIfEmptyProbeSeeder::class])
        ->expectsOutputToContain('running the seeders')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['email' => 'probe@example.com']);
});

it('skips the seeders when the database already has users', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('db:seed-if-empty', ['--class' => SeedIfEmptyProbeSeeder::class])
        ->expectsOutputToContain('seeding skipped')
        ->assertSuccessful();

    $this->assertDatabaseMissing('users', ['email' => 'probe@example.com']);
    $this->assertDatabaseCount('users', 1);
});
