<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| All routes here are protected by:
|   1. auth:sanctum  — must present a valid Bearer token
|   2. AdminMiddleware — user.role must be 'admin', otherwise 403
|
| Data sources:
|   - Users  → Laravel DB
|   - Orders → WHMCS
|   - Dashboard stats → Laravel DB (user count) + WHMCS (all other metrics)
|
*/

Route::prefix('admin')
    ->middleware(['auth:sanctum', AdminMiddleware::class])
    ->group(function () {

        // Dashboard — platform-wide aggregate stats
        Route::get('/dashboard', [AdminDashboardController::class, 'summary']);

        // Users (Laravel DB)
        Route::get('/users',       [AdminUserController::class, 'index']);
        Route::get('/users/{id}',  [AdminUserController::class, 'show']);

        // Orders (WHMCS — cross-client, no ownership filter)
        Route::get('/orders',      [AdminOrderController::class, 'index']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);

    });
