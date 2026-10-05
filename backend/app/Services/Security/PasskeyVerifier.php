<?php

namespace App\Services\Security;

use App\Contracts\Security\PasskeyCeremony;
use App\Contracts\Security\SecondFactorVerifier;
use App\Enums\PasskeyPurpose;
use App\Enums\SecondFactorContext;
use App\Models\Passkey;
use App\Models\User;

/**
 * Passkey as second factor. The payload carries the answer of the browser
 * (credential) and the context, added by VerifySecondFactorAction: the
 * options are those generated for that context and that user, so a passkey
 * of somebody else, or options of another operation, never pass. The
 * options are consumed by every attempt; a success saves the new signature
 * counter and the last use. The verified passkey stays available for the
 * events that name it.
 */
final class PasskeyVerifier implements SecondFactorVerifier
{
    private ?Passkey $verified = null;

    public function __construct(
        private readonly PasskeyCeremony $ceremony,
        private readonly PasskeyChallengeStore $challenges,
    ) {}

    public function verify(User $user, #[\SensitiveParameter] array $payload): bool
    {
        $this->verified = null;

        $context = $payload['context'] ?? null;
        $credential = $payload['credential'] ?? null;

        if (! $context instanceof SecondFactorContext || ! is_array($credential)) {
            return false;
        }

        $options = $this->challenges->pull(PasskeyPurpose::forSecondFactor($context));

        if ($options === null) {
            return false;
        }

        $this->verified = $this->ceremony->verify($credential, $options, $user);

        return $this->verified !== null;
    }

    public function verifiedPasskey(): ?Passkey
    {
        return $this->verified;
    }
}
