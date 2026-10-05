<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\CheckPasswordResetTokenAction;
use App\Actions\Security\GenerateSecondFactorPasskeyOptionsAction;
use App\Enums\SecondFactorContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckPasswordResetTokenRequest;
use App\Http\Resources\PasskeyOptionsResource;

/**
 * Passkey options of the password reset. The token comes first, as in the
 * reset itself: without a valid link nothing tells whether the account has
 * two-factor authentication or passkeys.
 */
final class ResetPasswordPasskeyOptionsController extends Controller
{
    public function __invoke(
        CheckPasswordResetTokenRequest $request,
        CheckPasswordResetTokenAction $checkToken,
        GenerateSecondFactorPasskeyOptionsAction $generateOptions,
    ): PasskeyOptionsResource {
        $user = $checkToken->execute($request->validated('email'), $request->validated('token'));

        return PasskeyOptionsResource::make($generateOptions->execute($user, SecondFactorContext::PasswordReset));
    }
}
