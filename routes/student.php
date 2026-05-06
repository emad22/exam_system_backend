<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Middleware\StudentOrDemoRole;

Route::middleware(['auth:sanctum', StudentOrDemoRole::class])->as('student.')->group(function () {
    // Exam Routes
    Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
    Route::post('/exams/{exam}/start', [ExamController::class, 'start'])->name('exams.start');
    Route::post('/exams/{exam}/reset-demo', [ExamController::class, 'resetDemo'])->name('exams.reset-demo');
    
    // Attempt Routes
    Route::prefix('attempts/{attempt}')->as('attempts.')->group(function () {
        Route::get('/', [ExamController::class, 'showAttempt'])->name('show');
        Route::get('/next-batch', [ExamController::class, 'getNextBatch'])->name('next-batch');
        Route::post('/submit-batch', [ExamController::class, 'submitBatch'])->name('submit-batch');
        Route::post('/timeout', [ExamController::class, 'timeout'])->name('timeout');
        Route::post('/completion', [ExamController::class, 'finish'])->name('completion');
        Route::patch('/progress', [ExamController::class, 'updateProgress'])->name('progress');
        Route::get('/results', [ExamController::class, 'results'])->name('results');
        Route::post('/warnings', [ExamController::class, 'logWarning'])->name('warnings');
    });

    // Certificate Routes
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
});

// Common Student/Auth Routes
Route::middleware('auth:sanctum')->as('student.')->group(function () {
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
});
