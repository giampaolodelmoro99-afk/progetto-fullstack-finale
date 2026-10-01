<?php

namespace App\Actions\Security;

use App\Enums\SecurityEventType;
use App\Exceptions\Auth\TwoFactorNotEnabledException;
use App\Models\User;
use App\Notifications\RecoveryCodesRegeneratedNotification;
use App\Support\Auth\RecoveryCodes;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * New recovery codes, returned in clear only here: the previous ones stop
 * working at once. The check runs under a row lock, so a deactivation in
 * parallel cannot leave recovery codes without the TOTP.
 */
final class RegenerateRecoveryCodesAction
{
    public function __construct(
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @return list<string>
     */
    public function execute(User $user): array
    {
        $codes = RecoveryCodes::generate(config('security.recovery_codes_count'));

        DB::transaction(function () use ($user, $codes): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $locked->hasSecondFactor()) {
                throw new TwoFactorNotEnabledException;
            }

            $locked->forceFill(['two_factor_recovery_codes' => RecoveryCodes::hash($codes)])->save();

            $this->recordSecurityEvent->execute($locked, SecurityEventType::RecoveryCodesRegenerated);
        });

        $user->refresh();

        $user->notify(
            (new RecoveryCodesRegeneratedNotification(now(), UserAgentLabel::for($this->request->userAgent())))->afterCommit(),
        );

        return $codes;
    }
}
