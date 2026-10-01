<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

final class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request, ResetPasswordAction $resetPassword): JsonResponse
    {
        $resetPassword->execute($request->validated());

        return response()->json([
            'message' => 'La password è stata reimpostata: accedi con la nuova password.',
        ]);
    }
}
