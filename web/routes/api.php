<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RevenueCatWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/revenuecat', [RevenueCatWebhookController::class, 'store'])
    ->middleware('throttle:60,1');

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::delete('/account', [AuthController::class, 'destroy']);
    });
});
