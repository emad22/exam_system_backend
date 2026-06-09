<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Proctoring\ProctoringController;

/**
 * Proctoring Routes
 * All routes require authentication
 */

Route::middleware(['auth:sanctum'])->group(function () {
    // Initiate proctoring session
    Route::post('/proctoring/session/initiate', [ProctoringController::class, 'initiateSession'])
        ->name('proctoring.session.initiate');

    // Get session details
    Route::get('/proctoring/session/{sessionId}', [ProctoringController::class, 'getSession'])
        ->name('proctoring.session.get');

    // Start recording
    Route::post('/proctoring/session/{sessionId}/start', [ProctoringController::class, 'startRecording'])
        ->name('proctoring.recording.start');

    // Pause recording
    Route::post('/proctoring/session/{sessionId}/pause', [ProctoringController::class, 'pauseRecording'])
        ->name('proctoring.recording.pause');

    // Resume recording
    Route::post('/proctoring/session/{sessionId}/resume', [ProctoringController::class, 'resumeRecording'])
        ->name('proctoring.recording.resume');

    // End session
    Route::post('/proctoring/session/{sessionId}/end', [ProctoringController::class, 'endSession'])
        ->name('proctoring.session.end');

    // Report violation
    Route::post('/proctoring/violation', [ProctoringController::class, 'reportViolation'])
        ->name('proctoring.violation.report');

    // Get violations
    Route::get('/proctoring/violations/{sessionId}', [ProctoringController::class, 'getViolations'])
        ->name('proctoring.violations.get');
});
