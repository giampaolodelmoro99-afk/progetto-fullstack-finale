<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    require __DIR__.'/api/v1/public.php';

    Route::middleware('auth:sanctum')->group(function (): void {
        require __DIR__.'/api/v1/customer.php';

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function (): void {
            require __DIR__.'/api/v1/admin.php';
        });
    });
});
