<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\SettingsResource;

final class SettingsController extends Controller
{
    public function __invoke(): SettingsResource
    {
        return SettingsResource::make(config('shop'));
    }
}
