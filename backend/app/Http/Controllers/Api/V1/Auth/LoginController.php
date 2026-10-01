<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginAction $login): JsonResponse
    {
        $result = $login->execute($request->validated('email'), $request->validated('password'));

        if ($result->requiresTwoFactor()) {
            return response()->json([
                'two_factor_required' => true,
                'methods' => $result->methods,
            ]);
        }

        return UserResource::make($result->user->load('pendingEmailChange'))->response();
    }
}
