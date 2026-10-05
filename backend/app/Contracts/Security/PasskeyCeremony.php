<?php

namespace App\Contracts\Security;

use App\Models\Passkey;
use App\Models\User;
use App\Support\Auth\PasskeyOptions;

/**
 * The WebAuthn cryptography, kept behind a contract so that the tests of
 * the flows replace it with a fake from the container. Keeping the options
 * in the session (single use, expiry, purpose) is not part of it: that is
 * PasskeyChallengeStore, real in every test.
 *
 * register() and verify() return null for any invalid answer of the
 * browser, so the callers answer with their own message.
 */
interface PasskeyCeremony
{
    /**
     * Registration options: discoverable credential and user verification
     * required, the passkeys of the user excluded.
     */
    public function creationOptions(User $user): PasskeyOptions;

    /**
     * Verification options with user verification required. Without a user
     * any discoverable credential is accepted (passwordless login); with a
     * user only the passkeys of that user.
     */
    public function requestOptions(?User $user = null): PasskeyOptions;

    /**
     * @param  array<string, mixed>  $credential
     */
    public function register(User $user, string $name, array $credential, string $options): ?Passkey;

    /**
     * Verifies the answer and saves the new signature counter and the last
     * use. With a user, a passkey of somebody else is rejected.
     *
     * @param  array<string, mixed>  $credential
     */
    public function verify(array $credential, string $options, ?User $user = null): ?Passkey;
}
