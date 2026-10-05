<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SettingsResource;

final class SettingsController extends Controller
{
    public function __invoke(): SettingsResource
    {
        return SettingsResource::make(config('shop'));
    }
}
