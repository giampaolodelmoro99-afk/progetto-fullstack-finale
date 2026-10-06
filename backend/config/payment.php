<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | Implementation of App\Contracts\PaymentGateway. "fake" is the simulated
    | gateway of the demo and of the tests: no real charge, the cards ending
    | in 0002 are rejected. A Stripe driver in test mode is an optional
    | extension.
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'fake'),

];
