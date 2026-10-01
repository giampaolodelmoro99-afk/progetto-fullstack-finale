<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SendVerificationEmailController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Il tuo indirizzo email è già confermato.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Ti abbiamo inviato un nuovo link di conferma.',
        ], Response::HTTP_ACCEPTED);
    }
}
