<?php

namespace App\Actions\Account;

use App\Actions\Security\RecordSecurityEventAction;
use App\Actions\Security\VerifySecondFactorAction;
use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;
use App\Enums\SecurityEventType;
use App\Http\Middleware\EnsureIdentityConfirmed;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Support\Auth\SecondFactorResult;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Password change from the Security page. The current password is checked
 * by the Form Request; with two-factor authentication the second factor is
 * asked in the same request, not through identity.confirmed, so the
 * password is never asked twice. It is verified before the transaction, so
 * the event of a wrong code is never rolled back, and it shares the failure
 * counter of every other context.
 *
 * Password, remember token, other sessions and event change together. The
 * current session is regenerated and loses its identity confirmation; the
 * AuthenticateSession middleware of Sanctum then stores the hash of the new
 * password, because the change is saved on the authenticated user itself.
 */
final class ChangePasswordAction
{
    public function __construct(
        private readonly Request $request,
        private readonly VerifySecondFactorAction $verifySecondFactor,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @param  array{password: string, method?: string|null, code?: string|null, credential?: array<string, mixed>|null}  $data
     */
    public function execute(User $user, #[\SensitiveParameter] array $data): ?SecondFactorResult
    {
        $secondFactor = $user->hasSecondFactor()
            ? $this->verifySecondFactor->execute(
                $user,
                SecondFactorMethod::tryFrom((string) ($data['method'] ?? '')),
                ['code' => $data['code'] ?? null, 'credential' => $data['credential'] ?? null],
                SecondFactorContext::PasswordChange,
            )
            : null;

        $session = $this->request->session();
        $currentSessionId = $session->getId();

        DB::transaction(function () use ($user, $data, $currentSessionId): void {
            $user->forceFill([
                'password' => $data['password'],
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table(config('session.table'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();

            $this->recordSecurityEvent->execute($user, SecurityEventType::PasswordChanged);
        });

        $session->forget(EnsureIdentityConfirmed::SESSION_KEY);
        $session->migrate(true);

        $user->notify(
            (new PasswordChangedNotification(now(), UserAgentLabel::for($this->request->userAgent())))->afterCommit(),
        );

        return $secondFactor;
    }
}
