<?php

namespace App\Providers;

use App\Contracts\AiAssistant;
use App\Services\Assistant\FakeAiAssistant;
use App\Services\Assistant\OllamaAssistant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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
    }
}
