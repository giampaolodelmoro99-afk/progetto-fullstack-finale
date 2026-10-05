<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Security\GenerateSecondFactorPasskeyOptionsAction;
use App\Enums\SecondFactorContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\PasskeyOptionsResource;
use Illuminate\Http\Request;

final class ConfirmIdentityPasskeyOptionsController extends Controller
{
    public function __invoke(Request $request, GenerateSecondFactorPasskeyOptionsAction $generateOptions): PasskeyOptionsResource
    {
        return PasskeyOptionsResource::make(
            $generateOptions->execute($request->user(), SecondFactorContext::IdentityConfirmation),
        );
    }
}
