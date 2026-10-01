<?php

namespace App\Providers;

use App\Contracts\AiAssistant;
use App\Models\User;
use App\Services\Assistant\FakeAiAssistant;
use App\Services\Assistant\OllamaAssistant;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->configurePasswords();
        $this->configureAuthentication();
        $this->configureRateLimiting();
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
     * Named limiters of the authentication routes and the general limiter
     * of the api group. The 429 response is rendered in bootstrap/app.php.
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
    }
}
