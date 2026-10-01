<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Security\DisableTwoFactorAction;
use App\Actions\Security\EnableTwoFactorAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\TwoFactorSetupResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TwoFactorController extends Controller
{
    public function store(Request $request, EnableTwoFactorAction $enableTwoFactor): TwoFactorSetupResource
    {
        return TwoFactorSetupResource::make($enableTwoFactor->execute($request->user()));
    }

    public function destroy(Request $request, DisableTwoFactorAction $disableTwoFactor): Response
    {
        $disableTwoFactor->execute($request->user());

        return response()->noContent();
    }
}
