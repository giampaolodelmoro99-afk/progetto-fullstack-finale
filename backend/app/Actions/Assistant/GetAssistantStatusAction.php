<?php

namespace App\Actions\Assistant;

use App\Contracts\AiAssistant;
use Illuminate\Support\Facades\Cache;

/**
 * Whether the assistant can answer, cached for a few seconds: the widget
 * asks on every page, and a stopped Ollama costs up to connect_timeout.
 * Only the status endpoint uses it; a message always tries the model.
 */
final class GetAssistantStatusAction
{
    public const CACHE_KEY = 'assistant:status';

    public const CACHE_SECONDS = 10;

    public function __construct(private readonly AiAssistant $assistant) {}

    public function execute(): bool
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): bool => $this->assistant->isAvailable());
    }
}
