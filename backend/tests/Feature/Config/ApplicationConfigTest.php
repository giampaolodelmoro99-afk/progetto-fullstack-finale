<?php

use App\Contracts\PaymentGateway;
use App\Services\Payment\FakePaymentGateway;
use Illuminate\Support\Facades\Route;

it('uses the italian timezone', function () {
    expect(config('app.timezone'))->toBe('Europe/Rome')
        ->and(now()->timezoneName)->toBe('Europe/Rome');
});

it('defines the shop settings in cents and pieces', function () {
    expect(config('shop'))->toBe([
        'shipping_cost' => 690,
        'free_shipping_threshold' => 9900,
        'new_arrival_days' => 30,
        'last_units_threshold' => 3,
        'low_stock_threshold' => 5,
        'max_quantity_per_item' => 10,
    ]);
});

it('defines the account security settings', function () {
    expect(config('security'))->toBe([
        'confirmation_timeout' => 600,
        'login_challenge_ttl' => 300,
        'passkey_challenge_ttl' => 300,
        'email_change_ttl' => 60,
        'recovery_codes_count' => 8,
        'totp_issuer' => 'Casa del Silicio',
        'security_events_per_page' => 10,
        'account_deletion_pending_window' => 15,
    ]);
});

it('defines the assistant settings', function () {
    expect(config('assistant'))->toBe([
        'driver' => 'fake',
        'base_url' => 'http://ollama:11434',
        'model' => 'casa-del-silicio',
        'think' => false,
        'connect_timeout' => 3,
        'timeout' => 120,
        'lock_margin' => 30,
        'max_messages' => 10,
        'max_message_length' => 500,
        'max_assistant_message_length' => 4000,
        'max_tool_results' => 8,
        'max_tool_rounds' => 3,
        'max_products' => 4,
        'max_concurrent' => 1,
    ]);
});

it('uses the simulated payment gateway', function () {
    expect(config('payment.driver'))->toBe('fake')
        ->and(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

it('refuses an unknown payment driver', function () {
    config(['payment.driver' => 'stripe']);

    expect(fn () => app(PaymentGateway::class))
        ->toThrow(InvalidArgumentException::class, 'Unsupported payment driver [stripe].');
});

it('points the email links to the frontend', function () {
    expect(config('app.frontend_url'))->toBe('http://localhost:5173');
});

it('keeps the email verification link valid for 24 hours and the reset link for 60 minutes', function () {
    expect(config('auth.verification.expire'))->toBe(1440)
        ->and(config('auth.passwords.users.expire'))->toBe(60);
});

it('binds the passkeys to the frontend, not to the backend', function () {
    expect(config('passkeys.relying_party_id'))->toBe('localhost')
        ->and(config('passkeys.allowed_origins'))->toBe(['http://localhost:5173'])
        ->and(config('passkeys.timeout'))->toBe(60000)
        ->and(config('passkeys.guard'))->toBe('web');
});

it('uses the fixed user handle secret of .env.testing in the tests', function () {
    expect(config('passkeys.user_handle_secret'))->toBe('3b23babc030e27b906247ae5590ce5f77c11a53f3e1ba748711399a9ee9482be');
});

it('leaves out the blade routes of laravel/passkeys', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'passkey.login-options',
    'passkey.login',
    'passkey.confirm-options',
    'passkey.confirm',
    'passkey.registration-options',
    'passkey.store',
    'passkey.destroy',
]);

it('blocks the session for 10 seconds on the routes that consume passkey options', function (string $name) {
    $route = Route::getRoutes()->getByName($name);

    expect($route->locksFor())->toBe(10)
        ->and($route->waitsFor())->toBe(10);
})->with([
    'v1.passkeys.login',
    'v1.passkeys.store',
    'v1.login.two-factor',
    'v1.identity.confirm',
    'v1.password.update',
    'v1.password.reset',
]);

it('does not block the session on the routes that only give options', function (string $name) {
    expect(Route::getRoutes()->getByName($name)->locksFor())->toBeNull();
})->with([
    'v1.passkeys.login.options',
    'v1.passkeys.options',
    'v1.login.two-factor.passkey-options',
]);
