<?php

use App\Http\Controllers\Bff\ActionController;
use App\Http\Controllers\Bff\ScreenController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/screens/{name}', [ScreenController::class, 'show']);
    Route::post('/actions/{name}', [ActionController::class, 'store']);
});
