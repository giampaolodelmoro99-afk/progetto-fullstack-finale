<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every Feature test runs on the ecommerce_testing database, rebuilt by
| RefreshDatabase. Helpers such as actingAsCustomer() and actingAsAdmin()
| are added here together with users and roles.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function frontendOrigin(): string
{
    return 'http://localhost:5173';
}
