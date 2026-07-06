<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Proctoring\ProctoringController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/proctoring/session/initiate', [ProctoringController::class, 'initiateSession'])
        ->name('proctoring.session.initiate');

    Route::post('/proctoring/verify-identity', [ProctoringController::class, 'verifyIdentity'])
        ->name('proctoring.identity.verify');

    // OCR: extract ID number from uploaded image (keeps Gemini API key server-side)
    Route::post('/proctoring/extract-id', [ProctoringController::class, 'extractId'])
        ->name('proctoring.extract-id');

    Route::get('/proctoring/session/{sessionId}', [ProctoringController::class, 'getSession'])
        ->name('proctoring.session.get');

    Route::post('/proctoring/session/{sessionId}/start', [ProctoringController::class, 'startRecording'])
        ->name('proctoring.recording.start');

    Route::post('/proctoring/session/{sessionId}/pause', [ProctoringController::class, 'pauseRecording'])
        ->name('proctoring.recording.pause');

    Route::post('/proctoring/session/{sessionId}/resume', [ProctoringController::class, 'resumeRecording'])
        ->name('proctoring.recording.resume');

    Route::post('/proctoring/session/{sessionId}/end', [ProctoringController::class, 'endSession'])
        ->name('proctoring.session.end');

    Route::post('/proctoring/session/{sessionId}/violation', [ProctoringController::class, 'reportViolation'])
        ->name('proctoring.violation.report');

    Route::post('/proctoring/session/{sessionId}/face-log', [ProctoringController::class, 'logFaceDetection'])
        ->name('proctoring.face-log.store');

    Route::get('/proctoring/session/{sessionId}/descriptor', [ProctoringController::class, 'getFaceDescriptor'])
        ->name('proctoring.session.descriptor');

    Route::get('/proctoring/session/{sessionId}/face-image', [ProctoringController::class, 'getFaceImage'])
        ->name('proctoring.session.face-image');

    Route::get('/proctoring/violations/{sessionId}', [ProctoringController::class, 'getViolations'])
        ->name('proctoring.violations.get');

    Route::post('/proctoring/session/{sessionId}/close', [ProctoringController::class, 'closeSession'])
        ->name('proctoring.session.close');

    // ✅ الجديد: تسجيل المهارة
    Route::post('/proctoring/session/{sessionId}/skill', [ProctoringController::class, 'recordSkill'])
        ->name('proctoring.session.skill');

    Route::post('/proctoring/session/{sessionId}/skill/exit', [ProctoringController::class, 'recordSkillExit'])
        ->name('proctoring.session.skill.exit');

    // ✅ الجديد: Admin يتحقق إن الجلسة لسه شغالة (للـ polling من الطالب)
    Route::get('/proctoring/session/{sessionId}/status', [ProctoringController::class, 'getSessionStatus'])
        ->name('proctoring.session.status');
});

// ✅ خارج Sanctum — navigator.sendBeacon لا يبعت الـ auth cookie بشكل مضمون
// مأمّن بـ session_token بدل الـ auth middleware
Route::post('/proctoring/session/{sessionId}/end-beacon', [ProctoringController::class, 'endSessionBeacon'])
    ->name('proctoring.session.end-beacon');