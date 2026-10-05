<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\CompleteTwoFactorLoginAction;
use App\Enums\SecondFactorMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class TwoFactorChallengeController extends Controller
{
    public function __invoke(TwoFactorChallengeRequest $request, CompleteTwoFactorLoginAction $completeLogin): JsonResponse
    {
        $result = $completeLogin->execute(
            SecondFactorMethod::from($request->validated('method')),
            [
                'code' => $request->validated('code'),
                'credential' => $request->validated('credential'),
            ],
        );

        return UserResource::make($result->user->load('pendingEmailChange'))
            ->additional($result->secondFactor->meta())
            ->response();
    }
}
