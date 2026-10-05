<?php

namespace Tests\Support;

use App\Contracts\Security\PasskeyCeremony;
use App\Models\Passkey;
use App\Models\User;
use App\Support\Auth\PasskeyOptions;
use Illuminate\Support\Str;

/**
 * Ceremony without cryptography for the tests of the flows, bound by
 * fakePasskeys(). The answer of the browser is simplified:
 *
 * - registration: { id, response: { attestationObject } } is accepted unless
 *   attestationObject is "invalid" or the options belong to another user;
 * - verification: { id, response: { signature } } is accepted when id is the
 *   credential_id of a passkey (of the given user, when there is one) and
 *   signature is not "invalid".
 *
 * Keeping the options in the session (single use, expiry, purpose) stays
 * real: PasskeyChallengeStore is never faked.
 */
final class FakePasskeyCeremony implements PasskeyCeremony
{
    public function creationOptions(User $user): PasskeyOptions
    {
        $options = ['challenge' => Str::random(43), 'user_id' => $user->id];

        return new PasskeyOptions(['challenge' => $options['challenge']], json_encode($options));
    }

    public function requestOptions(?User $user = null): PasskeyOptions
    {
        $options = ['challenge' => Str::random(43), 'user_id' => $user?->id];

        return new PasskeyOptions(['challenge' => $options['challenge']], json_encode($options));
    }

    public function register(User $user, string $name, array $credential, string $options): ?Passkey
    {
        $id = $credential['id'] ?? null;

        if (! is_string($id)
            || ($credential['response']['attestationObject'] ?? null) === 'invalid'
            || json_decode($options, true)['user_id'] !== $user->id
            || Passkey::query()->where('credential_id', $id)->exists()) {
            return null;
        }

        return Passkey::factory()->for($user)->create(['name' => $name, 'credential_id' => $id]);
    }

    public function verify(array $credential, string $options, ?User $user = null): ?Passkey
    {
        $passkey = Passkey::query()->where('credential_id', (string) ($credential['id'] ?? ''))->first();

        if ($passkey === null
            || ($user !== null && (int) $passkey->user_id !== $user->id)
            || ($credential['response']['signature'] ?? null) === 'invalid') {
            return null;
        }

        $passkey->forceFill(['last_used_at' => now()])->save();

        return $passkey;
    }
}
