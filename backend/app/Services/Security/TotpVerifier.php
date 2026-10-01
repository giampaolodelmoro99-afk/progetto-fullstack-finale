<?php

namespace App\Services\Security;

use App\Contracts\Security\SecondFactorVerifier;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * Six digit code of the authenticator app. One interval before and after
 * the current one is accepted, for clocks that are slightly off, and an
 * interval already used, or an older one, is rejected. Without a previous
 * interval the library gets 0, so it always returns the matching interval
 * instead of true. The current interval comes from now(), so time travel in
 * the tests moves it too.
 *
 * It also checks the secret that is not confirmed yet, when the user
 * activates two-factor authentication.
 */
final class TotpVerifier implements SecondFactorVerifier
{
    public const WINDOW = 1;

    public const PERIOD = 30;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function verify(User $user, #[\SensitiveParameter] array $payload): bool
    {
        $code = preg_replace('/\s+/', '', (string) ($payload['code'] ?? ''));

        if ($user->two_factor_secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $step = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            $code,
            $user->two_factor_last_used_step ?? 0,
            self::WINDOW,
            intdiv(now()->getTimestamp(), self::PERIOD),
        );

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_step' => $step])->save();

        return true;
    }
}
