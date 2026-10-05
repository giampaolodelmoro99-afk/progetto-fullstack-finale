<?php

namespace App\Actions\Security;

use App\Contracts\Security\PasskeyCeremony;
use App\Enums\PasskeyPurpose;
use App\Enums\SecondFactorContext;
use App\Exceptions\Auth\PasskeyUnavailableException;
use App\Models\User;
use App\Services\Security\PasskeyChallengeStore;
use App\Support\Auth\PasskeyOptions;

/**
 * Passkey options of a second factor: only the passkeys of the user, kept
 * in the session for the given context. Without two-factor authentication
 * the passkey is never asked as second factor, and without a passkey there
 * is nothing to offer: both answer passkey_unavailable.
 */
final class GenerateSecondFactorPasskeyOptionsAction
{
    public function __construct(
        private readonly PasskeyCeremony $ceremony,
        private readonly PasskeyChallengeStore $challenges,
    ) {}

    public function execute(User $user, SecondFactorContext $context): PasskeyOptions
    {
        if (! $user->hasSecondFactor() || ! $user->hasPasskeysEnabled()) {
            throw new PasskeyUnavailableException;
        }

        $options = $this->ceremony->requestOptions($user);
        $this->challenges->put(PasskeyPurpose::forSecondFactor($context), $options);

        return $options;
    }
}
