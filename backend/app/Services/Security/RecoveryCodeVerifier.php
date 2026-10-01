<?php

namespace App\Services\Security;

use App\Contracts\Security\SecondFactorVerifier;
use App\Models\User;
use App\Support\Auth\RecoveryCodes;
use Illuminate\Support\Facades\Hash;

/**
 * Single use recovery code. The code is compared with the bcrypt hashes of
 * the user, at most recovery_codes_count of them; the matching hash is
 * removed, so the same code never works twice.
 */
final class RecoveryCodeVerifier implements SecondFactorVerifier
{
    public function verify(User $user, #[\SensitiveParameter] array $payload): bool
    {
        $code = RecoveryCodes::normalize((string) ($payload['code'] ?? ''));
        $hashes = $user->two_factor_recovery_codes ?? [];

        if ($code === '') {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }
}
