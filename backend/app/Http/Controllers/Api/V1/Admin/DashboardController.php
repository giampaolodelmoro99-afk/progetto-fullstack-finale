<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\GetDashboardStatsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DashboardResource;

final class DashboardController extends Controller
{
    public function __invoke(GetDashboardStatsAction $getDashboardStats): DashboardResource
    {
        return DashboardResource::make($getDashboardStats->execute());
    }
}
