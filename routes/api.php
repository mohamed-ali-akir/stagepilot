<?php

use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\StatsController;
use Illuminate\Support\Facades\Route;

/*
 * REST API, version 1 (documented in docs/API.md).
 * Authentication with Laravel Sanctum: the session cookie (Vue module inside the app) or a personal token
 * ("Authorization: Bearer ..."). Limited to 60 requests a minute (HTTP 429 beyond).
 */
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::patch('applications/{application}/status', [ApplicationController::class, 'updateStatus'])->name('applications.status');

    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('stats', StatsController::class)->name('stats');
});
