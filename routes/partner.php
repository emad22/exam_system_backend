<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\Partner\PartnerReportController;
use App\Http\Controllers\Api\Partner\PartnerStudentController;
use App\Http\Controllers\Api\CertificateController;

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

Route::middleware(['auth:sanctum', 'single.session', \App\Http\Middleware\PartnerRole::class])
    ->prefix('partner')
    ->name('partner.')
    ->group(function () {

        // Dashboard Stats
        Route::get('/stats', [PartnerDashboardController::class, 'index'])->name('stats');

        // Students
        Route::get('/students', [PartnerStudentController::class, 'index'])->name('students.index');

        // Reports
        Route::get('/reports', [PartnerReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{attempt}', [PartnerReportController::class, 'show'])->name('reports.show');

        // Certificates
        Route::get('/certificates', [CertificateController::class, 'partnerIndex'])->name('certificates.index');
        Route::post('/certificates/bulk-download', [CertificateController::class, 'bulkDownload'])->name('certificates.bulk-download');
        Route::post('/certificates/create-for-attempt/{attempt}', [CertificateController::class, 'createForAttempt'])->name('certificates.create-for-attempt');

    });
