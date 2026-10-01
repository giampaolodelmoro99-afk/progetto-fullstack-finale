<?php

namespace App\Actions\Auth;

use App\Actions\Security\RecordSecurityEventAction;
use App\Enums\SecurityEventType;
use App\Exceptions\Auth\InvalidVerificationLinkException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Confirms the email address from the signed link, also for a guest: the
 * signature (checked by the signed middleware) ties the link to the user,
 * to the email hash and to the expiry. A missing user and a hash of another
 * address answer like an invalid signature. No session is started. The
 * verification and its security event are written in one transaction.
 */
final class VerifyEmailAction
{
    public function __construct(private readonly RecordSecurityEventAction $recordSecurityEvent) {}

    public function execute(string $id, string $hash): User
    {
        $user = User::query()->find($id);

        if ($user === null || ! hash_equals(hash('sha1', $user->getEmailForVerification()), $hash)) {
            throw new InvalidVerificationLinkException;
        }

        if (! $user->hasVerifiedEmail()) {
            DB::transaction(function () use ($user): void {
                $user->markEmailAsVerified();

                $this->recordSecurityEvent->execute($user, SecurityEventType::EmailVerified);
            });
        }

        return $user;
    }
}
