<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\GeneratePasskeyLoginOptionsAction;
use App\Actions\Auth\LoginWithPasskeyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasskeyLoginRequest;
use App\Http\Resources\PasskeyOptionsResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class PasskeyLoginController extends Controller
{
    public function options(GeneratePasskeyLoginOptionsAction $generateOptions): PasskeyOptionsResource
    {
        return PasskeyOptionsResource::make($generateOptions->execute());
    }

    public function store(PasskeyLoginRequest $request, LoginWithPasskeyAction $loginWithPasskey): JsonResponse
    {
        $user = $loginWithPasskey->execute($request->validated('credential'));

        return UserResource::make($user->load('pendingEmailChange'))->response();
    }
}
