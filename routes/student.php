<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExamSessionController;
use App\Http\Controllers\Api\ExamProgressController;
use App\Http\Controllers\Api\ExamSecurityController;
use App\Http\Controllers\Api\ExamResultController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Middleware\StudentOrDemoRole;

Route::middleware(['auth:sanctum', StudentOrDemoRole::class])->as('student.')->group(function () {
    // Exam Session Routes
    Route::get('/exams', [ExamSessionController::class, 'index'])->name('exams.index');
    Route::get('/exams/{exam}', [ExamSessionController::class, 'show'])->name('exams.show');
    Route::post('/exams/{exam}/start', [ExamSessionController::class, 'start'])->name('exams.start');
    Route::post('/exams/{exam}/reset-demo', [ExamSessionController::class, 'resetDemo'])->name('exams.reset-demo');
    
    // Attempt Routes (Split across Session, Progress, Security, and Results)
    Route::prefix('attempts/{attempt}')->as('attempts.')->group(function () {
        // Session-related
        Route::get('/', [ExamSessionController::class, 'showAttempt'])->name('show');
        Route::post('/completion', [ExamSessionController::class, 'finish'])->name('completion');

        // Progress-related
        Route::get('/next-batch', [ExamProgressController::class, 'getNextBatch'])->name('next-batch');
        Route::post('/submit-batch', [ExamProgressController::class, 'submitBatch'])->name('submit-batch');
        Route::patch('/progress', [ExamProgressController::class, 'updateProgress'])->name('progress');

        // Security-related
        Route::post('/timeout', [ExamSecurityController::class, 'timeout'])->name('timeout');
        Route::post('/warnings', [ExamSecurityController::class, 'logWarning'])->name('warnings');

        // Result-related
        Route::get('/results', [ExamResultController::class, 'results'])->name('results');
    });

    // Certificate Routes
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
});

// Common Student/Auth Routes
Route::middleware('auth:sanctum')->as('student.')->group(function () {
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
});
