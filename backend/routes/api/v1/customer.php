<?php

use App\Http\Controllers\Api\V1\Account\ConfirmIdentityController;
use App\Http\Controllers\Api\V1\Account\ConfirmTwoFactorController;
use App\Http\Controllers\Api\V1\Account\EmailChangeController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Account\RecoveryCodeController;
use App\Http\Controllers\Api\V1\Account\SecurityEventController;
use App\Http\Controllers\Api\V1\Account\SecurityOverviewController;
use App\Http\Controllers\Api\V1\Account\TwoFactorController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\SendVerificationEmailController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
| Customer routes, loaded inside the auth:sanctum group with the v1 prefix.
*/

Route::post('logout', LogoutController::class)->name('logout');

Route::get('me', [ProfileController::class, 'show'])->name('me.show');
Route::patch('me', [ProfileController::class, 'update'])->name('me.update');

/*
| The permission comes before the identity confirmation (priority list in
| bootstrap/app.php): an administrator gets 403 without being asked for the
| password.
*/
Route::delete('me', [ProfileController::class, 'destroy'])
    ->middleware(['can:delete,'.User::class, 'identity.confirmed'])
    ->name('me.destroy');

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

/*
| The second factor is asked in the form itself, so no identity.confirmed:
| the password would be asked twice.
*/
Route::put('me/password', [PasswordController::class, 'update'])
    ->middleware('throttle:identity-confirmation')
    ->name('password.update');

/*
| The throttle runs before identity.confirmed (middleware priority of
| Laravel), so a 423 or a 422 counts as an attempt too: known behaviour,
| described in the account-security skill.
*/
Route::post('me/email', [EmailChangeController::class, 'store'])
    ->middleware(['throttle:email-change', 'identity.confirmed'])
    ->name('email-change.store');

Route::delete('me/email-change', [EmailChangeController::class, 'destroy'])
    ->middleware('throttle:email-change')
    ->name('email-change.destroy');

Route::get('me/security', SecurityOverviewController::class)->name('security.show');

Route::get('me/security-events', [SecurityEventController::class, 'index'])->name('security-events.index');
