<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Security\GenerateSecondFactorPasskeyOptionsAction;
use App\Enums\SecondFactorContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\PasskeyOptionsResource;
use App\Services\Security\PendingLoginChallenge;

/**
 * Passkey options of the second step of the login, for the user of the
 * pending challenge.
 */
final class TwoFactorPasskeyOptionsController extends Controller
{
    public function __invoke(
        PendingLoginChallenge $pendingLogin,
        GenerateSecondFactorPasskeyOptionsAction $generateOptions,
    ): PasskeyOptionsResource {
        return PasskeyOptionsResource::make(
            $generateOptions->execute($pendingLogin->user(), SecondFactorContext::Login),
        );
    }
}
