<?php

namespace App\Actions\Auth;

use App\Actions\Security\RecordSecurityEventAction;
use App\Enums\SecurityEventType;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use App\Support\Auth\LoginResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;

/**
 * Password login. Without two-factor authentication it authenticates and
 * regenerates the session; with it, it only stores the pending challenge in
 * a regenerated session and the second factor completes the login.
 *
 * A failed attempt always lasts at least TIMEBOX_MICROSECONDS, as in the
 * SessionGuard of Laravel: an unknown email answers at once while a known
 * one first runs bcrypt, so without the timebox the response time would
 * reveal whether an address is registered.
 */
final class LoginAction
{
    public const PENDING_USER_KEY = 'login.pending_user_id';

    public const PENDING_AT_KEY = 'login.pending_at';

    public const TIMEBOX_MICROSECONDS = 200_000;

    public function __construct(
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
        private readonly Timebox $timebox,
    ) {}

    public function execute(string $email, #[\SensitiveParameter] string $password): LoginResult
    {
        return $this->timebox->call(function (Timebox $timebox) use ($email, $password): LoginResult {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                throw new InvalidCredentialsException;
            }

            if (! Hash::check($password, $user->password)) {
                $this->recordSecurityEvent->execute($user, SecurityEventType::LoginFailed);

                throw new InvalidCredentialsException;
            }

            $timebox->returnEarly();

            return $this->start($user);
        }, self::TIMEBOX_MICROSECONDS);
    }

    private function start(User $user): LoginResult
    {
        $session = $this->request->session();

        if ($user->hasSecondFactor()) {
            Auth::guard('web')->logout();
            $session->regenerate();
            $session->put([
                self::PENDING_USER_KEY => $user->id,
                self::PENDING_AT_KEY => now()->getTimestamp(),
            ]);

            return LoginResult::twoFactorRequired($user->secondFactorMethods());
        }

        $session->forget([self::PENDING_USER_KEY, self::PENDING_AT_KEY]);
        Auth::guard('web')->login($user);
        $session->regenerate();

        $this->recordSecurityEvent->execute($user, SecurityEventType::Login);

        return LoginResult::authenticated($user);
    }
}
