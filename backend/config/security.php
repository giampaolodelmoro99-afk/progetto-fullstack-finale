<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identity confirmation and login challenge
    |--------------------------------------------------------------------------
    |
    | Seconds for which an identity confirmation stays valid in the session,
    | and seconds available to complete the second factor after the password.
    |
    */

    'confirmation_timeout' => 600,

    'login_challenge_ttl' => 300,

    /*
    |--------------------------------------------------------------------------
    | Email change
    |--------------------------------------------------------------------------
    |
    | Minutes after which a pending email change request expires.
    |
    */

    'email_change_ttl' => 60,

    /*
    |--------------------------------------------------------------------------
    | Two-factor recovery codes and security events
    |--------------------------------------------------------------------------
    */

    'recovery_codes_count' => 8,

    'security_events_per_page' => 10,

];
