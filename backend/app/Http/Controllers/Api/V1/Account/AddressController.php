<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\CreateAddressAction;
use App\Actions\Account\DeleteAddressAction;
use App\Actions\Account\UpdateAddressAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\Address\StoreAddressRequest;
use App\Http\Requests\Account\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class AddressController extends Controller
{
    /**
     * The addresses of the user, the default one first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection(
            $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
        );
    }

    public function store(StoreAddressRequest $request, CreateAddressAction $createAddress): JsonResponse
    {
        $address = $createAddress->execute($request->user(), $request->validated());

        return AddressResource::make($address)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Address $address): AddressResource
    {
        Gate::authorize('view', $address);

        return AddressResource::make($address);
    }

    public function update(UpdateAddressRequest $request, Address $address, UpdateAddressAction $updateAddress): AddressResource
    {
        return AddressResource::make($updateAddress->execute($address, $request->validated()));
    }

    public function destroy(Address $address, DeleteAddressAction $deleteAddress): Response
    {
        Gate::authorize('delete', $address);

        $deleteAddress->execute($address);

        return response()->noContent();
    }
}
