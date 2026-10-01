<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Account\CancelEmailChangeByTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailChangeTokenRequest;
use Illuminate\Http\Response;

final class CancelEmailChangeController extends Controller
{
    public function __invoke(EmailChangeTokenRequest $request, CancelEmailChangeByTokenAction $cancelEmailChange): Response
    {
        $cancelEmailChange->execute($request->validated('token'));

        return response()->noContent();
    }
}
