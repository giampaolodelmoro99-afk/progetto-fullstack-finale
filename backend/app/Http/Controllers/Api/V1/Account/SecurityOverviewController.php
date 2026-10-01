<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecurityOverviewResource;
use Illuminate\Http\Request;

final class SecurityOverviewController extends Controller
{
    public function __invoke(Request $request): SecurityOverviewResource
    {
        return SecurityOverviewResource::make($request->user());
    }
}
