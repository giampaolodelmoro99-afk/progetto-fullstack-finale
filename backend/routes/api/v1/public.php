<?php

use App\Http\Controllers\Api\V1\System\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('health');
