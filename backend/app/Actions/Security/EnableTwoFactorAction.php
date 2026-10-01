<?php

namespace App\Actions\Security;

use App\Exceptions\Auth\TwoFactorAlreadyEnabledException;
use App\Models\User;
use App\Services\Security\TwoFactorQrCode;
use App\Support\Auth\TwoFactorSetup;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

/**
 * First step of the activation: a new secret, not confirmed yet, so
 * hasSecondFactor() stays false until ConfirmTwoFactorAction. Starting
 * again replaces the secret waiting for confirmation. An active two-factor
 * authentication is never replaced: it would silently invalidate the
 * authenticator app of the user. The check runs under a row lock, so a
 * confirmation that succeeds in parallel cannot be overwritten.
 */
final class EnableTwoFactorAction
{
    public const SECRET_LENGTH = 32;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly TwoFactorQrCode $qrCode,
    ) {}

    public function execute(User $user): TwoFactorSetup
    {
        $secret = $this->google2fa->generateSecretKey(self::SECRET_LENGTH);

        DB::transaction(function () use ($user, $secret): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->hasSecondFactor()) {
                throw new TwoFactorAlreadyEnabledException;
            }

            $locked->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_step' => null,
            ])->save();
        });

        $user->refresh();

        $url = $this->qrCode->url($user->email, $secret);

        return new TwoFactorSetup($secret, $url, $this->qrCode->svg($url));
    }
}
