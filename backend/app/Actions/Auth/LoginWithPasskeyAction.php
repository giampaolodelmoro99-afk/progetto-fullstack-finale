<?php

namespace App\Actions\Auth;

use App\Actions\Security\RecordSecurityEventAction;
use App\Contracts\Security\PasskeyCeremony;
use App\Enums\PasskeyPurpose;
use App\Enums\SecurityEventType;
use App\Exceptions\Auth\InvalidPasskeyException;
use App\Http\Middleware\EnsureIdentityConfirmed;
use App\Models\User;
use App\Services\Security\PasskeyChallengeStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Passwordless login: the passkey alone is enough, also with two-factor
 * authentication active, because it already proves possession of the
 * device and the user verification (fingerprint, face or PIN).
 *
 * The options of the login are single use and expire after
 * passkey_challenge_ttl seconds. Every failure, an anonymized account
 * included, is the same InvalidPasskeyException. On success a pending
 * password challenge and an identity confirmation left in the session are
 * dropped, the session is regenerated and the login event names the passkey.
 */
final class LoginWithPasskeyAction
{
    public function __construct(
        private readonly Request $request,
        private readonly PasskeyCeremony $ceremony,
        private readonly PasskeyChallengeStore $challenges,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $credential
     */
    public function execute(array $credential): User
    {
        $options = $this->challenges->pull(PasskeyPurpose::Login);
        $passkey = $options === null ? null : $this->ceremony->verify($credential, $options);
        $user = $passkey?->user;

        if (! $user instanceof User || $user->isAnonymized()) {
            throw new InvalidPasskeyException;
        }

        $session = $this->request->session();
        $session->forget([LoginAction::PENDING_USER_KEY, LoginAction::PENDING_AT_KEY, EnsureIdentityConfirmed::SESSION_KEY]);
        Auth::guard('web')->login($user);
        $session->regenerate();

        $this->recordSecurityEvent->execute($user, SecurityEventType::Login, ['passkey_name' => $passkey->name]);

        return $user;
    }
}
