<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\CheckPasswordResetTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckPasswordResetTokenRequest;
use Illuminate\Http\JsonResponse;

final class CheckPasswordResetTokenController extends Controller
{
    public function __invoke(CheckPasswordResetTokenRequest $request, CheckPasswordResetTokenAction $checkToken): JsonResponse
    {
        $user = $checkToken->execute($request->validated('email'), $request->validated('token'));

        return response()->json([
            'requires_second_factor' => $user->hasSecondFactor(),
            'methods' => $user->secondFactorMethods(),
        ]);
    }
}
