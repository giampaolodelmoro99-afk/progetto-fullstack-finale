<?php

namespace App\Actions\Security;

use App\Contracts\Security\SecondFactorVerifier;
use App\Enums\SecondFactorContext;
use App\Enums\SecondFactorMethod;
use App\Enums\SecurityEventType;
use App\Exceptions\Auth\InvalidSecondFactorException;
use App\Models\User;
use App\Notifications\RecoveryCodeUsedNotification;
use App\Services\Security\PasskeyVerifier;
use App\Services\Security\RecoveryCodeVerifier;
use App\Services\Security\TotpVerifier;
use App\Support\Auth\SecondFactorResult;
use App\Support\UserAgentLabel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Second factor of an access check: login, password reset, identity
 * confirmation and password change. Used only when the user
 * hasSecondFactor().
 *
 * A missing method, code or passkey answer (credential) is a required
 * error, not a failed attempt: no event is written.
 *
 * The user is read again with a row lock, so two parallel requests with the
 * same code cannot both pass. The verifier receives the context too, which
 * the passkey needs to find the options of the operation. A failure is
 * recorded after the transaction of this action, with the context and the
 * method but never the code. Callers must not run it inside a transaction
 * of their own: a rollback would erase the event of the failed attempt. A
 * used recovery code writes its event and sends a notice with the codes
 * left; a passkey leaves its name in the result.
 *
 * Wrong codes and passkeys count per user, with one counter shared by every
 * context and method: after MAX_FAILURES in DECAY_SECONDS every
 * verification of that user is a 429, even with a right code, which is not
 * consumed. The route limiters alone would leave uncovered the password
 * reset, protected only by the link, and every future route that accepts a
 * second factor. Accepted trade-off: whoever knows the password, a reset
 * link or a session can lock the second factor of the user for a minute.
 *
 * Each verifier is resolved from the container, so that the tests can
 * replace what it depends on with a fake.
 */
final class VerifySecondFactorAction
{
    public const MAX_FAILURES = 5;

    public const DECAY_SECONDS = 60;

    private const VERIFIERS = [
        SecondFactorMethod::Totp->value => TotpVerifier::class,
        SecondFactorMethod::RecoveryCode->value => RecoveryCodeVerifier::class,
        SecondFactorMethod::Passkey->value => PasskeyVerifier::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        User $user,
        ?SecondFactorMethod $method,
        #[\SensitiveParameter] array $payload,
        SecondFactorContext $context,
    ): SecondFactorResult {
        $this->ensureGiven($method, $payload);
        $this->ensureNotLocked($user);

        /** @var SecondFactorVerifier $verifier */
        $verifier = $this->container->make(self::VERIFIERS[$method->value]);

        $verified = DB::transaction(function () use ($user, $verifier, $payload, $context): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            return $verifier->verify($locked, [...$payload, 'context' => $context]);
        });

        $user->refresh();

        if (! $verified) {
            RateLimiter::hit(self::throttleKey($user), self::DECAY_SECONDS);

            $this->recordSecurityEvent->execute($user, SecurityEventType::TwoFactorChallengeFailed, [
                'context' => $context->value,
                'method' => $method->value,
            ]);

            throw InvalidSecondFactorException::forMethod($method);
        }

        $result = new SecondFactorResult(
            $method,
            $user->recovery_codes_remaining,
            $verifier instanceof PasskeyVerifier ? $verifier->verifiedPasskey()?->name : null,
        );

        if ($method === SecondFactorMethod::RecoveryCode) {
            $this->recordSecurityEvent->execute($user, SecurityEventType::RecoveryCodeUsed);

            $user->notify((new RecoveryCodeUsedNotification(
                now(),
                UserAgentLabel::for($this->request->userAgent()),
                $result->recoveryCodesRemaining,
            ))->afterCommit());
        }

        return $result;
    }

    private function ensureNotLocked(User $user): void
    {
        $key = self::throttleKey($user);

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            throw new ThrottleRequestsException(headers: ['Retry-After' => RateLimiter::availableIn($key)]);
        }
    }

    private static function throttleKey(User $user): string
    {
        return 'second-factor-failures:'.$user->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureGiven(?SecondFactorMethod $method, #[\SensitiveParameter] array $payload): void
    {
        $errors = [];

        if ($method === null) {
            $errors['method'] = __('validation.required', ['attribute' => __('validation.attributes.method')]);
        }

        if ($method === SecondFactorMethod::Passkey) {
            $credential = $payload['credential'] ?? null;

            if (! is_array($credential) || $credential === []) {
                $errors['credential'] = __('validation.required', ['attribute' => __('validation.attributes.credential')]);
            }
        } elseif (blank($payload['code'] ?? null)) {
            $errors['code'] = __('validation.required', ['attribute' => __('validation.attributes.code')]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
