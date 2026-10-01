<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecurityEventResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SecurityEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = $request->user()
            ->securityEvents()
            ->latest('created_at')
            ->latest('id')
            ->paginate(config('security.security_events_per_page'));

        return SecurityEventResource::collection($events);
    }
}
