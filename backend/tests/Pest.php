<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every Feature test runs on the ecommerce_testing database, rebuilt by
| RefreshDatabase. Unit tests boot the application too (configuration and
| time travel), but never touch the database. Helpers such as
| actingAsCustomer() and actingAsAdmin() are added here with the endpoints.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function frontendOrigin(): string
{
    return 'http://localhost:5173';
}
