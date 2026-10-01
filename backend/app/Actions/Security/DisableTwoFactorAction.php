<?php

namespace App\Actions\Security;

use App\Enums\SecurityEventType;
use App\Exceptions\Auth\TwoFactorNotEnabledException;
use App\Models\User;
use App\Notifications\TwoFactorDisabledNotification;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns two-factor authentication off: secret, recovery codes and last used
 * interval are erased, and every other session of the user is closed. The
 * remember token changes too, so a remember cookie of a closed session
 * cannot open a new one. Passkeys (Phase 3c) stay. The check runs under a
 * row lock, as in the other two-factor actions.
 */
final class DisableTwoFactorAction
{
    public function __construct(
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(User $user): void
    {
        $currentSessionId = $this->request->hasSession() ? $this->request->session()->getId() : null;

        DB::transaction(function () use ($user, $currentSessionId): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $locked->hasSecondFactor()) {
                throw new TwoFactorNotEnabledException;
            }

            $locked->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_step' => null,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table(config('session.table'))
                ->where('user_id', $user->id)
                ->when($currentSessionId !== null, fn ($query) => $query->where('id', '!=', $currentSessionId))
                ->delete();

            $this->recordSecurityEvent->execute($locked, SecurityEventType::TwoFactorDisabled);
        });

        $user->refresh();

        $user->notify(
            (new TwoFactorDisabledNotification(now(), UserAgentLabel::for($this->request->userAgent())))->afterCommit(),
        );
    }
}
