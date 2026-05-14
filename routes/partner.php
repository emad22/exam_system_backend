<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\Partner\PartnerReportController;

/*
|--------------------------------------------------------------------------
| Partner Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. They are also protected by
| 'auth:sanctum' and 'PartnerRole' middlewares.
|
*/

Route::middleware(['auth:sanctum', \App\Http\Middleware\PartnerRole::class])
    ->prefix('partner')
    ->name('partner.')
    ->group(function () {
        
        // Dashboard Stats
        Route::get('/stats', [PartnerDashboardController::class, 'index'])->name('stats');
        
        // Reports
        Route::get('/reports', [PartnerReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{attempt}', [PartnerReportController::class, 'show'])->name('reports.show');
        
    });
