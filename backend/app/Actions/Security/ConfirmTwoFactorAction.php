<?php

namespace App\Actions\Security;

use App\Enums\SecurityEventType;
use App\Exceptions\Auth\InvalidSecondFactorException;
use App\Exceptions\Auth\TwoFactorAlreadyEnabledException;
use App\Exceptions\Auth\TwoFactorSetupMissingException;
use App\Models\User;
use App\Notifications\TwoFactorEnabledNotification;
use App\Services\Security\TotpVerifier;
use App\Support\Auth\RecoveryCodes;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Second step of the activation: a valid code of the secret waiting for
 * confirmation turns two-factor authentication on and creates the recovery
 * codes, returned in clear only here. A wrong code is not an access check,
 * so it writes no two_factor_challenge_failed event.
 *
 * The row lock keeps two parallel confirmations from creating two sets of
 * codes; the interval of the code is recorded, so it cannot log in again.
 */
final class ConfirmTwoFactorAction
{
    public function __construct(
        private readonly Request $request,
        private readonly TotpVerifier $totpVerifier,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @return list<string>
     */
    public function execute(User $user, #[\SensitiveParameter] string $code): array
    {
        $codes = DB::transaction(function () use ($user, $code): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->hasSecondFactor()) {
                throw new TwoFactorAlreadyEnabledException;
            }

            if ($locked->two_factor_secret === null) {
                throw new TwoFactorSetupMissingException;
            }

            if (! $this->totpVerifier->verify($locked, ['code' => $code])) {
                throw new InvalidSecondFactorException;
            }

            $codes = RecoveryCodes::generate(config('security.recovery_codes_count'));

            $locked->forceFill([
                'two_factor_recovery_codes' => RecoveryCodes::hash($codes),
                'two_factor_confirmed_at' => now(),
            ])->save();

            $this->recordSecurityEvent->execute($locked, SecurityEventType::TwoFactorEnabled);

            return $codes;
        });

        $user->refresh();

        $user->notify(
            (new TwoFactorEnabledNotification(now(), UserAgentLabel::for($this->request->userAgent())))->afterCommit(),
        );

        return $codes;
    }
}
