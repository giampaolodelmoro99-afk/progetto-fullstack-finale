<?php

namespace App\Providers;

use App\Actions\Auth\LoginAction;
use App\Contracts\AiAssistant;
use App\Contracts\ClientConnection;
use App\Contracts\PaymentGateway;
use App\Contracts\Security\PasskeyCeremony;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Assistant\FakeAiAssistant;
use App\Services\Assistant\OllamaAssistant;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Security\WebAuthnPasskeyCeremony;
use App\Support\Auth\PasskeyUserHandleSecret;
use App\Support\PhpClientConnection;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Laravel\Passkeys\Passkeys;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FakeAiAssistant::class);

        $this->app->bind(AiAssistant::class, fn ($app): AiAssistant => match (config('assistant.driver')) {
            'fake' => $app->make(FakeAiAssistant::class),
            'ollama' => $app->make(OllamaAssistant::class),
            default => throw new InvalidArgumentException('Unsupported assistant driver ['.config('assistant.driver').'].'),
        });

        $this->app->bind(ClientConnection::class, PhpClientConnection::class);

        $this->app->singleton(FakePaymentGateway::class);

        $this->app->bind(PaymentGateway::class, fn ($app): PaymentGateway => match (config('payment.driver')) {
            'fake' => $app->make(FakePaymentGateway::class),
            default => throw new InvalidArgumentException('Unsupported payment driver ['.config('payment.driver').'].'),
        });

        $this->registerPasskeys();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->configurePasswords();
        $this->configureAuthentication();
        $this->configurePasskeys();
        $this->configureRateLimiting();
    }

    /**
     * laravel/passkeys gives only its actions: its routes, built for Blade
     * and redirects, are replaced by the API routes of the project, and its
     * model by App\Models\Passkey. The tests replace the ceremony with a
     * fake from the container.
     */
    private function registerPasskeys(): void
    {
        Passkeys::ignoreRoutes();
        Passkeys::usePasskeyModel(Passkey::class);

        $this->app->bind(PasskeyCeremony::class, WebAuthnPasskeyCeremony::class);
    }

    /**
     * Without PASSKEYS_USER_HANDLE_SECRET the secret of the user handle is
     * derived from the APP_KEY. Without an APP_KEY either (only before the
     * entrypoint generates it) it stays empty, and a passkey ceremony fails
     * instead of using a value shared by every installation.
     */
    private function configurePasskeys(): void
    {
        $appKey = config('app.key');

        if (blank(config('passkeys.user_handle_secret')) && is_string($appKey) && $appKey !== '') {
            config(['passkeys.user_handle_secret' => PasskeyUserHandleSecret::derive($appKey)]);
        }
    }

    /**
     * At least 10 characters with upper and lower case letters and numbers.
     * The leaked password check calls an external service, so it is off
     * in the test environment.
     */
    private function configurePasswords(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(10)->mixedCase()->numbers();

            return $this->app->environment('testing') ? $rule : $rule->uncompromised();
        });
    }

    /**
     * Cookie-only authentication, and email links that open the frontend.
     */
    private function configureAuthentication(): void
    {
        Sanctum::getAccessTokenFromRequestUsing(fn (): ?string => null);

        VerifyEmail::createUrlUsing(function (User $user): string {
            $signedUrl = URL::temporarySignedRoute(
                'v1.verification.verify',
                now()->addMinutes(config('auth.verification.expire')),
                ['id' => $user->getKey(), 'hash' => hash('sha1', $user->getEmailForVerification())],
                absolute: false,
            );

            parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $signature);

            return config('app.frontend_url').'/verify-email?'.http_build_query([
                'id' => $user->getKey(),
                'hash' => hash('sha1', $user->getEmailForVerification()),
                'expires' => $signature['expires'],
                'signature' => $signature['signature'],
            ]);
        });

        ResetPassword::createUrlUsing(fn (User $user, string $token): string => config('app.frontend_url').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }

    /**
     * Named limiters of the authentication routes and of the assistant, and
     * the general limiter of the api group. The 429 response is rendered in
     * bootstrap/app.php.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perHour(5)
            ->by($request->ip()));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinutes(10, 3)
            ->by(Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()));

        RateLimiter::for('verification', fn (Request $request): Limit => Limit::perMinute(6)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(self::twoFactorThrottleKey($request)));

        RateLimiter::for('identity-confirmation', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->user()->getAuthIdentifier()));

        RateLimiter::for('email-change', fn (Request $request): Limit => Limit::perHour(3)
            ->by($request->user()->getAuthIdentifier()));

        RateLimiter::for('passkey-login', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->ip()));

        RateLimiter::for('assistant', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * The two-factor attempts count per user, not per session: whoever knows
     * the password could otherwise open new sessions to multiply the
     * attempts on the code. The login challenge names its user; the
     * activation has the authenticated one. Without either, the session or
     * the IP address.
     */
    private static function twoFactorThrottleKey(Request $request): string
    {
        $pendingUserId = $request->hasSession() ? $request->session()->get(LoginAction::PENDING_USER_KEY) : null;
        $userId = $pendingUserId ?? $request->user()?->getAuthIdentifier();

        if ($userId !== null) {
            return 'user:'.$userId;
        }

        return $request->hasSession() ? 'session:'.$request->session()->getId() : 'ip:'.$request->ip();
    }
}
