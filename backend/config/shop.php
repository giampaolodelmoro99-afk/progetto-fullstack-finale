<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    |
    | Amounts in cents. Shipping is free when the subtotal reaches the
    | threshold; otherwise the fixed cost applies.
    |
    */

    'shipping_cost' => 690,

    'free_shipping_threshold' => 9900,

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    |
    | new_arrival_days: window of the "new" badge and of the new=1 filter.
    | last_units_threshold: "Ultimi N pezzi" shown to customers.
    | low_stock_threshold: low stock in the admin area (restocking).
    | max_quantity_per_item: maximum quantity of a product in a cart row.
    |
    */

    'new_arrival_days' => 30,

    'last_units_threshold' => 3,

    'low_stock_threshold' => 5,

    'max_quantity_per_item' => 10,

];
