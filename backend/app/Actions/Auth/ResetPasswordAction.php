<?php

namespace App\Actions\Auth;

use App\Actions\Security\RecordSecurityEventAction;
use App\Enums\SecurityEventType;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Order of the checks: token, then second factor, then reset. The token
 * comes first so that a request without a valid link never learns whether
 * the account has two-factor authentication.
 *
 * Until the second factor verification exists (Phase 3b), a user with
 * two-factor authentication cannot reset the password: a missing factor is
 * a required error and a given one is rejected as invalid.
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
    ) {}

    /**
     * @param  array{email: string, token: string, password: string, method?: string|null, code?: string|null}  $data
     */
    public function execute(#[\SensitiveParameter] array $data): User
    {
        $user = $this->checkToken->execute($data['email'], $data['token']);

        if ($user->hasSecondFactor()) {
            $this->rejectSecondFactor($data);
        }

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

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rejectSecondFactor(array $data): never
    {
        $given = filled($data['method'] ?? null) && filled($data['code'] ?? null);

        throw ValidationException::withMessages([
            'code' => $given
                ? 'Codice non valido.'
                : __('validation.required', ['attribute' => __('validation.attributes.code')]),
        ]);
    }
}
