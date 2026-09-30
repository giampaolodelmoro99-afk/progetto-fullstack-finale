<?php

namespace App\Contracts;

use App\Exceptions\Assistant\AssistantFailedException;
use App\Exceptions\Assistant\AssistantTimeoutException;
use App\Exceptions\Assistant\AssistantUnavailableException;
use App\Services\Assistant\Events\TextDelta;
use App\Services\Assistant\Events\ToolCallRequested;
use Carbon\CarbonInterface;

interface AiAssistant
{
    /**
     * Whether the model service is reachable and the shop model is installed.
     */
    public function isAvailable(): bool;

    /**
     * Sends the conversation to the model and returns its answer as a sequence
     * of events. The request is opened before this method returns, so an
     * unreachable service surfaces here and not while iterating.
     *
     * The caller runs the requested tools and calls this method again with the
     * assistant tool calls and the tool results appended, until the model
     * answers with text only.
     *
     * @param  list<array{role: string, content: string, tool_calls?: list<array{name: string, arguments: array<string, mixed>}>, tool_name?: string}>  $messages  roles: user, assistant, tool
     * @param  list<array<string, mixed>>  $tools  tool definitions (JSON schema), the only source of shop data
     * @param  CarbonInterface|null  $deadline  moment after which the answer is abandoned
     * @return iterable<TextDelta|ToolCallRequested>
     *
     * @throws AssistantUnavailableException before the answer starts
     * @throws AssistantTimeoutException while iterating, past the deadline
     * @throws AssistantFailedException while iterating, on any other error
     */
    public function stream(array $messages, array $tools = [], ?CarbonInterface $deadline = null): iterable;
}
