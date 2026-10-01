<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\SendPasswordResetLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;

/**
 * Always the same answer, whether the address is registered or not.
 */
final class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request, SendPasswordResetLinkAction $sendResetLink): JsonResponse
    {
        $sendResetLink->execute($request->validated('email'));

        return response()->json([
            'message' => 'Se l\'indirizzo è registrato, riceverai un\'email con il link per reimpostare la password.',
        ]);
    }
}
