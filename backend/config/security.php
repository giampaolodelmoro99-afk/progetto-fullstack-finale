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
    | Passkey challenge
    |--------------------------------------------------------------------------
    |
    | Seconds for which the options of a passkey ceremony, kept in the
    | session, can be used once.
    |
    */

    'passkey_challenge_ttl' => 300,

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

    /*
    |--------------------------------------------------------------------------
    | Authenticator app
    |--------------------------------------------------------------------------
    |
    | Name shown by the authenticator app next to the account email.
    |
    */

    'totp_issuer' => 'Casa del Silicio',

    'security_events_per_page' => 10,

    /*
    |--------------------------------------------------------------------------
    | Account deletion
    |--------------------------------------------------------------------------
    |
    | Minutes for which a pending order counts as a payment in progress and
    | blocks the account deletion. An older pending order is an orphan.
    |
    */

    'account_deletion_pending_window' => 15,

];
