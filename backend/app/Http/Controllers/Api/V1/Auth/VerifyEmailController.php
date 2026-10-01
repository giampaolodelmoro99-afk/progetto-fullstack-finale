<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\VerifyEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\VerifiedEmailResource;

/**
 * Public route protected only by the signature of the link: it also works
 * for a guest who opens the email on another device.
 */
final class VerifyEmailController extends Controller
{
    public function __invoke(string $id, string $hash, VerifyEmailAction $verifyEmail): VerifiedEmailResource
    {
        return VerifiedEmailResource::make($verifyEmail->execute($id, $hash));
    }
}
