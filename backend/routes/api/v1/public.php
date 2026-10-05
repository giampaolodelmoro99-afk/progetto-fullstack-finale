<?php

use App\Http\Controllers\Api\V1\Auth\CancelEmailChangeController;
use App\Http\Controllers\Api\V1\Auth\CheckPasswordResetTokenController;
use App\Http\Controllers\Api\V1\Auth\ConfirmEmailChangeController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasskeyLoginController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordPasskeyOptionsController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorPasskeyOptionsController;
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

/*
| The routes that consume the options of a passkey ceremony block the
| session (lock in the cache, 10 seconds): two parallel requests of the same
| session run one after the other, so the second one finds the options
| already used. Without the lock both could read them before the first one
| saves the session, and authenticators that keep the signature counter at
| 0, such as synced passkeys, would not stop the replay.
*/
Route::post('login/two-factor', TwoFactorChallengeController::class)
    ->middleware('throttle:two-factor')
    ->block()
    ->name('login.two-factor');

Route::post('login/two-factor/passkey-options', TwoFactorPasskeyOptionsController::class)
    ->middleware('throttle:two-factor')
    ->name('login.two-factor.passkey-options');

/*
| Passwordless login: the user is unknown until the passkey is verified, so
| one limiter per IP address covers options and verification.
*/
Route::post('passkeys/login/options', [PasskeyLoginController::class, 'options'])
    ->middleware('throttle:passkey-login')
    ->name('passkeys.login.options');

Route::post('passkeys/login', [PasskeyLoginController::class, 'store'])
    ->middleware('throttle:passkey-login')
    ->block()
    ->name('passkeys.login');

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

Route::post('reset-password', ResetPasswordController::class)
    ->block()
    ->name('password.reset');

Route::post('reset-password/passkey-options', ResetPasswordPasskeyOptionsController::class)->name('password.reset.passkey-options');

/*
| Links of the email change: they work for a guest too, protected by the
| token (high entropy, 60 minutes, single use) and the api limiter.
*/
Route::post('email-change/confirm', ConfirmEmailChangeController::class)->name('email-change.confirm');

Route::post('email-change/cancel', CancelEmailChangeController::class)->name('email-change.cancel');
