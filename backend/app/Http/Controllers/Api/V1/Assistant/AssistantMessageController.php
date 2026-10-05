<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Actions\Assistant\StartAssistantAnswerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\SendAssistantMessageRequest;
use App\Services\Assistant\AssistantAnswerStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AssistantMessageController extends Controller
{
    public function __invoke(
        SendAssistantMessageRequest $request,
        StartAssistantAnswerAction $startAnswer,
        AssistantAnswerStream $stream,
    ): StreamedResponse {
        return $stream->toResponse($startAnswer->execute($request->conversation()));
    }
}
