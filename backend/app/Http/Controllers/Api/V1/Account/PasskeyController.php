<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Security\DeletePasskeyAction;
use App\Actions\Security\GeneratePasskeyRegistrationOptionsAction;
use App\Actions\Security\RegisterPasskeyAction;
use App\Actions\Security\RenamePasskeyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StorePasskeyRequest;
use App\Http\Requests\Account\UpdatePasskeyRequest;
use App\Http\Resources\PasskeyOptionsResource;
use App\Http\Resources\PasskeyResource;
use App\Models\Passkey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class PasskeyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PasskeyResource::collection(
            $request->user()->passkeys()->latest()->latest('id')->get(),
        );
    }

    public function options(Request $request, GeneratePasskeyRegistrationOptionsAction $generateOptions): PasskeyOptionsResource
    {
        return PasskeyOptionsResource::make($generateOptions->execute($request->user()));
    }

    public function store(StorePasskeyRequest $request, RegisterPasskeyAction $registerPasskey): JsonResponse
    {
        $passkey = $registerPasskey->execute(
            $request->user(),
            $request->validated('name'),
            $request->validated('credential'),
        );

        return PasskeyResource::make($passkey)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePasskeyRequest $request, Passkey $passkey, RenamePasskeyAction $renamePasskey): PasskeyResource
    {
        return PasskeyResource::make($renamePasskey->execute($passkey, $request->validated('name')));
    }

    public function destroy(Request $request, Passkey $passkey, DeletePasskeyAction $deletePasskey): Response
    {
        $deletePasskey->execute($request->user(), $passkey);

        return response()->noContent();
    }
}
