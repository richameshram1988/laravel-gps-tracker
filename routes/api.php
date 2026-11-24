<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\LocationController;
use App\Http\Controllers\API\ReportController;
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    ///locations//
    Route::post('/locations', [LocationController::class, 'store'])
        ->middleware('throttle:120,1');
    ///////////////// Report///

    Route::get('/reports/summary', [ReportController::class, 'summary']);
    Route::get('/reports/users/{user}', [ReportController::class, 'user']);
    Route::get('/reports/export', [ReportController::class, 'export']);
    Route::get('/reports/map-based-route/{user}', [ReportController::class, 'mapbased']);

});