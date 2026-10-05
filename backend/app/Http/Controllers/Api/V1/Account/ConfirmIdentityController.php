<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Security\ConfirmIdentityAction;
use App\Enums\SecondFactorMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmIdentityRequest;
use App\Http\Resources\IdentityConfirmationResource;
use Illuminate\Http\JsonResponse;

final class ConfirmIdentityController extends Controller
{
    public function __invoke(ConfirmIdentityRequest $request, ConfirmIdentityAction $confirmIdentity): JsonResponse
    {
        $confirmation = $confirmIdentity->execute(
            $request->user(),
            SecondFactorMethod::tryFrom((string) $request->validated('method')),
            [
                'code' => $request->validated('code'),
                'credential' => $request->validated('credential'),
            ],
        );

        return IdentityConfirmationResource::make($confirmation)
            ->additional($confirmation->secondFactor?->meta() ?? [])
            ->response();
    }
}
