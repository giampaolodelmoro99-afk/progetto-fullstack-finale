<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\OrderController;
use App\Http\Controllers\Api\V1\Admin\OrderStatusController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\ProductImageController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Admin routes, loaded inside the auth:sanctum and role:admin group with the
| v1/admin prefix. Products, categories and orders are bound on the id;
| the binding leaves out soft deleted products, so they answer 404.
*/
Route::get('settings', SettingsController::class)->name('settings');

Route::get('dashboard', DashboardController::class)->name('dashboard');

Route::apiResource('products', ProductController::class);

Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');

Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
    ->scopeBindings()
    ->name('products.images.destroy');

Route::apiResource('categories', CategoryController::class)->except(['show']);

Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::patch('orders/{order}/status', OrderStatusController::class)->name('orders.status');
