<?php

it('gives the shipping settings to a guest, in cents', function () {
    $this->getJson('/api/v1/shop/settings')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'shipping_cost' => 690,
                'free_shipping_threshold' => 9900,
            ],
        ]);
});

it('follows the shop configuration', function () {
    config(['shop.shipping_cost' => 500, 'shop.free_shipping_threshold' => 12000]);

    $this->getJson('/api/v1/shop/settings')
        ->assertOk()
        ->assertExactJson(['data' => ['shipping_cost' => 500, 'free_shipping_threshold' => 12000]]);
});
