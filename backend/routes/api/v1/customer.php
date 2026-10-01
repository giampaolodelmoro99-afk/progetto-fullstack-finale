<?php

use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\SendVerificationEmailController;
use Illuminate\Support\Facades\Route;

/*
| Customer routes, loaded inside the auth:sanctum group with the v1 prefix.
*/

Route::post('logout', LogoutController::class)->name('logout');

Route::get('me', [ProfileController::class, 'show'])->name('me.show');
Route::patch('me', [ProfileController::class, 'update'])->name('me.update');

Route::post('email/verification-notification', SendVerificationEmailController::class)
    ->middleware('throttle:verification')
    ->name('verification.send');
