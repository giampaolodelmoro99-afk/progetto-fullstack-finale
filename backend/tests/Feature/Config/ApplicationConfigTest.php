<?php

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

it('points the email links to the frontend', function () {
    expect(config('app.frontend_url'))->toBe('http://localhost:5173');
});

it('keeps the email verification link valid for 24 hours and the reset link for 60 minutes', function () {
    expect(config('auth.verification.expire'))->toBe(1440)
        ->and(config('auth.passwords.users.expire'))->toBe(60);
});
