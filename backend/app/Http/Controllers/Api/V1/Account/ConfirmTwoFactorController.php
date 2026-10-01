<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Security\ConfirmTwoFactorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmTwoFactorRequest;
use App\Http\Resources\RecoveryCodesResource;

final class ConfirmTwoFactorController extends Controller
{
    public function __invoke(ConfirmTwoFactorRequest $request, ConfirmTwoFactorAction $confirmTwoFactor): RecoveryCodesResource
    {
        return RecoveryCodesResource::make($confirmTwoFactor->execute($request->user(), $request->validated('code')));
    }
}
