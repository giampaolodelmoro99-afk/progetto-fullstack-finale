<?php

/*
|--------------------------------------------------------------------------
| Passkeys (laravel/passkeys)
|--------------------------------------------------------------------------
|
| Only the keys used by the project: the routes of the package are disabled
| (Passkeys::ignoreRoutes() in AppServiceProvider), so its middleware,
| throttle and redirect settings do not apply.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Relying party and allowed origins
    |--------------------------------------------------------------------------
    |
    | The ceremonies run in the browser on the frontend, not on the backend:
    | the relying party is the host of FRONTEND_URL (localhost) and the only
    | allowed origin is FRONTEND_URL itself (http://localhost:5173).
    |
    */

    'relying_party_id' => parse_url(env('FRONTEND_URL', 'http://localhost:5173'), PHP_URL_HOST),

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User handle secret
    |--------------------------------------------------------------------------
    |
    | Secret of the opaque WebAuthn user handle. Never a fixed value in the
    | repository: when PASSKEYS_USER_HANDLE_SECRET is empty, AppServiceProvider
    | derives it from the APP_KEY of the installation.
    |
    */

    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Browser timeout and guard
    |--------------------------------------------------------------------------
    |
    | Milliseconds given to the browser for a ceremony. The server side
    | lifetime of the options is security.passkey_challenge_ttl.
    |
    */

    'timeout' => 60000,

    'guard' => 'web',

];
