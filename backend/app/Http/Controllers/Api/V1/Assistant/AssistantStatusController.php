<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Actions\Assistant\GetAssistantStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssistantStatusResource;

final class AssistantStatusController extends Controller
{
    public function __invoke(GetAssistantStatusAction $getStatus): AssistantStatusResource
    {
        return AssistantStatusResource::make($getStatus->execute());
    }
}
