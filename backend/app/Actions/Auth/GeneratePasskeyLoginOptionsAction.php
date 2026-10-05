<?php

namespace App\Actions\Auth;

use App\Contracts\Security\PasskeyCeremony;
use App\Enums\PasskeyPurpose;
use App\Services\Security\PasskeyChallengeStore;
use App\Support\Auth\PasskeyOptions;

/**
 * Options of the passwordless login: no user and no list of credentials,
 * so the browser offers the discoverable passkeys of the site.
 */
final class GeneratePasskeyLoginOptionsAction
{
    public function __construct(
        private readonly PasskeyCeremony $ceremony,
        private readonly PasskeyChallengeStore $challenges,
    ) {}

    public function execute(): PasskeyOptions
    {
        $options = $this->ceremony->requestOptions();
        $this->challenges->put(PasskeyPurpose::Login, $options);

        return $options;
    }
}
