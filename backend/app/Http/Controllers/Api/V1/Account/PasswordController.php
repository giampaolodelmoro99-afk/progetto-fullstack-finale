<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\ChangePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Resources\PasswordChangedResource;
use Illuminate\Http\JsonResponse;

final class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, ChangePasswordAction $changePassword): JsonResponse
    {
        $user = $request->user();
        $secondFactor = $changePassword->execute($user, $request->validated());

        return PasswordChangedResource::make($user)
            ->additional($secondFactor?->meta() ?? [])
            ->response();
    }
}
