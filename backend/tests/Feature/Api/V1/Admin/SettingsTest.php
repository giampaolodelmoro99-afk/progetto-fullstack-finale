<?php

/**
 * The order states of the filter in the order list, in the order of the enum.
 *
 * @return list<array{value: string, label: string}>
 */
function expectedOrderStatuses(): array
{
    return [
        ['value' => 'pending', 'label' => 'In attesa'],
        ['value' => 'paid', 'label' => 'Pagato'],
        ['value' => 'shipped', 'label' => 'Spedito'],
        ['value' => 'delivered', 'label' => 'Consegnato'],
        ['value' => 'cancelled', 'label' => 'Annullato'],
    ];
}

it('gives the shop settings, the low stock threshold and the order states to the admin', function () {
    actingAsAdmin();

    $this->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'shipping_cost' => 690,
                'free_shipping_threshold' => 9900,
                'low_stock_threshold' => 5,
                'order_statuses' => expectedOrderStatuses(),
            ],
        ]);
});

it('follows the shop configuration', function () {
    config(['shop.shipping_cost' => 500, 'shop.free_shipping_threshold' => 12000, 'shop.low_stock_threshold' => 8]);
    actingAsAdmin();

    $this->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'shipping_cost' => 500,
                'free_shipping_threshold' => 12000,
                'low_stock_threshold' => 8,
                'order_statuses' => expectedOrderStatuses(),
            ],
        ]);
});
