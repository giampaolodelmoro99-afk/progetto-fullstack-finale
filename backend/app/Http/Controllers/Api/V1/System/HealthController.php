<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Actions\System\CheckDatabaseConnectionAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class HealthController extends Controller
{
    public function __invoke(CheckDatabaseConnectionAction $checkDatabase): JsonResponse
    {
        return $checkDatabase->execute()
            ? response()->json(['status' => 'ok'])
            : response()->json(['status' => 'unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
