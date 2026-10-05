<?php

namespace App\Services\Security;

use App\Enums\PasskeyPurpose;
use App\Support\Auth\PasskeyOptions;
use Illuminate\Http\Request;

/**
 * Keeps the options of a passkey ceremony in the session, one slot for each
 * purpose. New options replace the previous ones of the same purpose. The
 * options are single use: pull() always removes them, also when they are
 * expired or the answer then fails, and returns them only within
 * passkey_challenge_ttl seconds.
 */
final class PasskeyChallengeStore
{
    private const SESSION_PREFIX = 'passkey_challenges.';

    public function __construct(private readonly Request $request) {}

    public function put(PasskeyPurpose $purpose, PasskeyOptions $options): void
    {
        $this->request->session()->put(self::SESSION_PREFIX.$purpose->value, [
            'options' => $options->serialized,
            'created_at' => now()->getTimestamp(),
        ]);
    }

    public function pull(PasskeyPurpose $purpose): ?string
    {
        $entry = $this->request->session()->pull(self::SESSION_PREFIX.$purpose->value);

        if (! is_array($entry) || ! is_string($entry['options'] ?? null) || ! is_int($entry['created_at'] ?? null)) {
            return null;
        }

        if (now()->getTimestamp() - $entry['created_at'] > config('security.passkey_challenge_ttl')) {
            return null;
        }

        return $entry['options'];
    }
}
