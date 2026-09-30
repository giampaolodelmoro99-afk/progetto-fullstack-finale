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
        'security_events_per_page' => 10,
    ]);
});
