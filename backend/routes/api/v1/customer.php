<?php

use App\Http\Controllers\Api\V1\Account\AddressController;
use App\Http\Controllers\Api\V1\Account\CancelOrderController;
use App\Http\Controllers\Api\V1\Account\ConfirmIdentityController;
use App\Http\Controllers\Api\V1\Account\ConfirmIdentityPasskeyOptionsController;
use App\Http\Controllers\Api\V1\Account\ConfirmTwoFactorController;
use App\Http\Controllers\Api\V1\Account\EmailChangeController;
use App\Http\Controllers\Api\V1\Account\OrderController;
use App\Http\Controllers\Api\V1\Account\PasskeyController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\PasswordPasskeyOptionsController;
use App\Http\Controllers\Api\V1\Account\ProfileController;
use App\Http\Controllers\Api\V1\Account\RecoveryCodeController;
use App\Http\Controllers\Api\V1\Account\SecurityEventController;
use App\Http\Controllers\Api\V1\Account\SecurityOverviewController;
use App\Http\Controllers\Api\V1\Account\TwoFactorController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\SendVerificationEmailController;
use App\Http\Controllers\Api\V1\Cart\CartController;
use App\Http\Controllers\Api\V1\Cart\CartItemController;
use App\Http\Controllers\Api\V1\Checkout\CheckoutController;
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

/*
| block(): the routes that consume the options of a passkey ceremony run one
| at a time for each session (see routes/api/v1/public.php).
*/
Route::post('me/confirm-identity', ConfirmIdentityController::class)
    ->middleware('throttle:identity-confirmation')
    ->block()
    ->name('identity.confirm');

Route::post('me/confirm-identity/passkey-options', ConfirmIdentityPasskeyOptionsController::class)
    ->middleware('throttle:identity-confirmation')
    ->name('identity.passkey-options');

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
    ->block()
    ->name('password.update');

Route::post('me/password/passkey-options', PasswordPasskeyOptionsController::class)
    ->middleware('throttle:identity-confirmation')
    ->name('password.update.passkey-options');

/*
| Passkeys. The registration needs a recent identity confirmation for the
| options and for the save: a 423 on the save leaves the options in the
| session, so the frontend confirms and repeats it. The permission comes
| before the identity confirmation, so a passkey of somebody else answers
| 404 without asking for the password.
*/
Route::get('me/passkeys', [PasskeyController::class, 'index'])->name('passkeys.index');

Route::post('me/passkeys/options', [PasskeyController::class, 'options'])
    ->middleware('identity.confirmed')
    ->name('passkeys.options');

Route::post('me/passkeys', [PasskeyController::class, 'store'])
    ->middleware('identity.confirmed')
    ->block()
    ->name('passkeys.store');

Route::patch('me/passkeys/{passkey}', [PasskeyController::class, 'update'])->name('passkeys.update');

Route::delete('me/passkeys/{passkey}', [PasskeyController::class, 'destroy'])
    ->middleware(['can:delete,passkey', 'identity.confirmed'])
    ->name('passkeys.destroy');

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

/*
| Addresses, cart, checkout and orders: customers only (UserPolicy::purchase,
| 403 for an administrator, checked before the policies of the single
| resources and before the email verification). Only the checkout needs a
| verified email. Every write of the cart answers with the updated cart.
| block(): a second checkout of the same session (a double click on "Paga")
| waits for the first one and finds the cart already emptied, instead of
| creating and charging a second order.
*/
Route::middleware('can:purchase,'.User::class)->group(function (): void {
    Route::apiResource('addresses', AddressController::class);

    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    Route::delete('cart', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
    Route::patch('cart/items/{cartItem}', [CartItemController::class, 'update'])->name('cart.items.update');
    Route::delete('cart/items/{cartItem}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');

    Route::post('checkout', CheckoutController::class)
        ->middleware('verified')
        ->block()
        ->name('checkout');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/cancel', CancelOrderController::class)->name('orders.cancel');
});
