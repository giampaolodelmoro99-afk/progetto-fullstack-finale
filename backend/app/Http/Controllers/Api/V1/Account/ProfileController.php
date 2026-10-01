<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('pendingEmailChange'));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return UserResource::make($user->load('pendingEmailChange'));
    }
}
