<?php

namespace App\Actions\Auth;

use App\Actions\Security\RecordSecurityEventAction;
use App\Actions\Security\VerifySecondFactorAction;
use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;
use App\Enums\SecurityEventType;
use App\Notifications\PasswordResetNotification;
use App\Support\Auth\SecondFactorResult;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Order of the checks: token, then second factor, then reset. The token
 * comes first so that a request without a valid link never learns whether
 * the account has two-factor authentication.
 *
 * With two-factor authentication the second factor is verified before the
 * reset transaction, so the event of a wrong code is never rolled back. The
 * result of the second factor is returned for the codes left.
 *
 * After the reset every session of the user is closed and there is no
 * automatic login. Password, token, sessions and security event change in
 * one transaction; the notice leaves only after the commit, so a failed
 * email never leaves a reset without its event.
 */
final class ResetPasswordAction
{
    public function __construct(
        private readonly Request $request,
        private readonly CheckPasswordResetTokenAction $checkToken,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
        private readonly VerifySecondFactorAction $verifySecondFactor,
    ) {}

    /**
     * @param  array{email: string, token: string, password: string, method?: string|null, code?: string|null}  $data
     */
    public function execute(#[\SensitiveParameter] array $data): ?SecondFactorResult
    {
        $user = $this->checkToken->execute($data['email'], $data['token']);

        $secondFactor = $user->hasSecondFactor()
            ? $this->verifySecondFactor->execute(
                $user,
                SecondFactorMethod::tryFrom((string) ($data['method'] ?? '')),
                ['code' => $data['code'] ?? null],
                SecondFactorContext::PasswordReset,
            )
            : null;

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => $data['password'],
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            Password::broker()->deleteToken($user);

            DB::table(config('session.table'))->where('user_id', $user->id)->delete();

            $this->recordSecurityEvent->execute($user, SecurityEventType::PasswordReset);
        });

        $user->notify(
            (new PasswordResetNotification(now(), UserAgentLabel::for($this->request->userAgent())))->afterCommit(),
        );

        return $secondFactor;
    }
}
