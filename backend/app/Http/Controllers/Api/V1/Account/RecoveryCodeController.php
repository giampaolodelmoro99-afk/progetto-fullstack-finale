<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Security\RegenerateRecoveryCodesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecoveryCodesResource;
use Illuminate\Http\Request;

final class RecoveryCodeController extends Controller
{
    public function store(Request $request, RegenerateRecoveryCodesAction $regenerateRecoveryCodes): RecoveryCodesResource
    {
        return RecoveryCodesResource::make($regenerateRecoveryCodes->execute($request->user()));
    }
}
