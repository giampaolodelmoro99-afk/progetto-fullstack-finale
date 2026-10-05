<?php

it('gives the shop settings and the low stock threshold to the admin, in cents', function () {
    actingAsAdmin();

    $this->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'shipping_cost' => 690,
                'free_shipping_threshold' => 9900,
                'low_stock_threshold' => 5,
            ],
        ]);
});

it('follows the shop configuration', function () {
    config(['shop.shipping_cost' => 500, 'shop.free_shipping_threshold' => 12000, 'shop.low_stock_threshold' => 8]);
    actingAsAdmin();

    $this->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertExactJson(['data' => ['shipping_cost' => 500, 'free_shipping_threshold' => 12000, 'low_stock_threshold' => 8]]);
});
