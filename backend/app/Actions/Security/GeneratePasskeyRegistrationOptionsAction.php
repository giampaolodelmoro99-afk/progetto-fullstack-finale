<?php

namespace App\Actions\Security;

use App\Contracts\Security\PasskeyCeremony;
use App\Enums\PasskeyPurpose;
use App\Models\User;
use App\Services\Security\PasskeyChallengeStore;
use App\Support\Auth\PasskeyOptions;

/**
 * Registration options for the authenticated user, kept in the session for
 * passkey_challenge_ttl seconds. They require a discoverable credential,
 * so every passkey also works for the passwordless login, and the user
 * verification (fingerprint, face or PIN).
 */
final class GeneratePasskeyRegistrationOptionsAction
{
    public function __construct(
        private readonly PasskeyCeremony $ceremony,
        private readonly PasskeyChallengeStore $challenges,
    ) {}

    public function execute(User $user): PasskeyOptions
    {
        $options = $this->ceremony->creationOptions($user);
        $this->challenges->put(PasskeyPurpose::Registration, $options);

        return $options;
    }
}
