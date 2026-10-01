<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Account\ConfirmEmailChangeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailChangeTokenRequest;
use App\Http\Resources\VerifiedEmailResource;

final class ConfirmEmailChangeController extends Controller
{
    public function __invoke(EmailChangeTokenRequest $request, ConfirmEmailChangeAction $confirmEmailChange): VerifiedEmailResource
    {
        return VerifiedEmailResource::make($confirmEmailChange->execute($request->validated('token')));
    }
}
