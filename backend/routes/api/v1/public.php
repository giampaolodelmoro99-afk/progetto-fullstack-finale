<?php

use App\Http\Controllers\Api\V1\Auth\CheckPasswordResetTokenController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\System\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('health');

Route::post('register', RegisterController::class)
    ->middleware('throttle:register')
    ->name('register');

Route::post('login', LoginController::class)
    ->middleware('throttle:login')
    ->name('login');

Route::post('login/two-factor', TwoFactorChallengeController::class)
    ->middleware('throttle:two-factor')
    ->name('login.two-factor');

/*
| Protected only by the signature of the link, not by authentication: the
| throttle comes first so that invalid signatures count too. The signature
| covers the path and the query, not the host, so a link generated from
| 127.0.0.1 still works when the frontend calls localhost.
*/
Route::post('email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['throttle:verification', 'signed:relative'])
    ->name('verification.verify');

Route::post('forgot-password', ForgotPasswordController::class)
    ->middleware('throttle:password-reset')
    ->name('password.email');

Route::post('reset-password/check', CheckPasswordResetTokenController::class)->name('password.check');

Route::post('reset-password', ResetPasswordController::class)->name('password.reset');
