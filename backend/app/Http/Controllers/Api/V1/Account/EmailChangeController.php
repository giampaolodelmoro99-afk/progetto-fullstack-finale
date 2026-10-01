<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\CancelPendingEmailChangeAction;
use App\Actions\Account\RequestEmailChangeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RequestEmailChangeRequest;
use App\Http\Resources\PendingEmailChangeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class EmailChangeController extends Controller
{
    public function store(RequestEmailChangeRequest $request, RequestEmailChangeAction $requestEmailChange): JsonResponse
    {
        return PendingEmailChangeResource::make($requestEmailChange->execute($request->user(), $request->validated('email')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, CancelPendingEmailChangeAction $cancelPendingEmailChange): Response
    {
        $cancelPendingEmailChange->execute($request->user());

        return response()->noContent();
    }
}
