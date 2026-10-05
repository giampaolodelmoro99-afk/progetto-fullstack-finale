<?php

namespace App\Support\Auth;

/**
 * Secret of the WebAuthn user handle when PASSKEYS_USER_HANDLE_SECRET is
 * not set. It is derived from the APP_KEY of the installation, so it is
 * never a fixed value of the repository and differs on every installation,
 * and an HMAC with its own label keeps it distinct from the APP_KEY itself
 * (key separation).
 */
final class PasskeyUserHandleSecret
{
    private const LABEL = 'passkeys.user_handle';

    public static function derive(string $appKey): string
    {
        return hash_hmac('sha256', self::LABEL, $appKey);
    }
}
