<?php

use App\Http\Controllers\Api\V1\Account\ConfirmIdentityController;
use App\Http\Controllers\Api\V1\Account\ConfirmTwoFactorController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Account\RecoveryCodeController;
use App\Http\Controllers\Api\V1\Account\TwoFactorController;
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

Route::post('me/confirm-identity', ConfirmIdentityController::class)
    ->middleware('throttle:identity-confirmation')
    ->name('identity.confirm');

Route::post('me/two-factor', [TwoFactorController::class, 'store'])
    ->middleware('identity.confirmed')
    ->name('two-factor.store');

Route::post('me/two-factor/confirm', ConfirmTwoFactorController::class)
    ->middleware('throttle:two-factor')
    ->name('two-factor.confirm');

Route::post('me/two-factor/recovery-codes', [RecoveryCodeController::class, 'store'])
    ->middleware('identity.confirmed')
    ->name('two-factor.recovery-codes');

Route::delete('me/two-factor', [TwoFactorController::class, 'destroy'])
    ->middleware('identity.confirmed')
    ->name('two-factor.destroy');
