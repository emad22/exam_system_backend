<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\QuestionImportController;
use App\Http\Middleware\AdminRole;
use App\Http\Middleware\StaffRole;
use App\Http\Controllers\Api\CertificateController;

Route::prefix('admin')->middleware(['auth:sanctum', StaffRole::class])->as('admin.')->group(function () {
    Route::get('/stats', [Admin\DashboardController::class, 'stats'])->name('stats');

    // Student Management
    Route::prefix('students')->as('students.')->group(function () {
        Route::get('/', [Admin\StudentController::class, 'index'])->name('index');
        Route::get('/template', [Admin\StudentController::class, 'downloadTemplate'])->name('template');
        Route::post('/bulk-delete', [Admin\StudentController::class, 'bulkDestroy'])->name('bulk-delete');
        Route::post('/bulk-skills', [Admin\StudentController::class, 'bulkUpdateSkills'])->name('bulk-update-skills');
        Route::get('/bulk-skills-export', [Admin\StudentController::class, 'exportSkillsExcel'])->name('export-skills');
        Route::post('/bulk-skills-import', [Admin\StudentController::class, 'importSkillsExcel'])->name('import-skills');
        Route::post('/batch', [Admin\StudentController::class, 'batchImport'])->name('batch-import');
        Route::post('/', [Admin\StudentController::class, 'store'])->name('store');
        Route::get('/{student}', [Admin\StudentController::class, 'show'])->name('show');
        Route::patch('/{student}', [Admin\StudentController::class, 'update'])->name('update');
        Route::delete('/{student}', [Admin\StudentController::class, 'destroy'])->name('destroy');
        Route::post('/{student}/reset', [Admin\StudentController::class, 'resetExamAttempts'])->name('reset');
    });

    // Partner Management
    Route::prefix('partners')->as('partners.')->group(function () {
        Route::get('/', [Admin\PartnerController::class, 'index'])->name('index');
        Route::post('/', [Admin\PartnerController::class, 'store'])->name('store');
        Route::get('/active', [Admin\PartnerController::class, 'getActivePartners'])->name('active');
        Route::get('/{partner}', [Admin\PartnerController::class, 'show'])->name('show');
        Route::patch('/{partner}', [Admin\PartnerController::class, 'update'])->name('update');
        Route::delete('/{partner}', [Admin\PartnerController::class, 'destroy'])->name('destroy');
        Route::post('/{partner}/hold', [Admin\PartnerController::class, 'deactivatePartnerStudents'])->name('hold');
        Route::post('/{partner}/unhold', [Admin\PartnerController::class, 'unholdPartner'])->name('unhold');
    });

    // Exam Management
    Route::prefix('exams')->as('exams.')->group(function () {
        Route::get('/', [Admin\ExamController::class, 'index'])->name('index');
        Route::post('/', [Admin\ExamController::class, 'store'])->name('store');
        Route::get('/{exam}', [Admin\ExamController::class, 'show'])->name('show');
        Route::patch('/{exam}', [Admin\ExamController::class, 'update'])->name('update');
        Route::patch('/{exam}/set-default', [Admin\ExamController::class, 'setDefault'])->name('set-default');
        Route::delete('/{exam}', [Admin\ExamController::class, 'destroy'])->name('destroy');
        Route::post('/import-folder', [QuestionImportController::class, 'importFolder'])->name('import-folder');
    });

    // Skill & Level Management
    Route::get('/skills-with-levels', [Admin\SkillController::class, 'getSkillsWithLevels'])->name('skills.with-levels');

    Route::prefix('skills')->as('skills.')->group(function () {
        Route::get('/', [Admin\SkillController::class, 'index'])->name('index');
        Route::post('/', [Admin\SkillController::class, 'store'])->name('store');
        Route::patch('/{skill}', [Admin\SkillController::class, 'update'])->name('update');
        Route::delete('/{skill}', [Admin\SkillController::class, 'destroy'])->name('destroy');
        Route::get('/{skill}/levels', [Admin\SkillController::class, 'getSkillWithLevels'])->name('levels');
        Route::post('/{skill}/levels/bulk', [Admin\SkillController::class, 'bulkUpdateLevels'])->name('bulk-update-levels');
    });
    
    Route::prefix('levels')->as('levels.')->group(function () {
        Route::get('/', [Admin\LevelController::class, 'index'])->name('index');
        Route::get('/{level}', [Admin\LevelController::class, 'show'])->name('show');
        Route::post('/', [Admin\LevelController::class, 'store'])->name('store');
        Route::patch('/{level}', [Admin\LevelController::class, 'update'])->name('update');
        Route::delete('/{level}', [Admin\LevelController::class, 'destroy'])->name('destroy');
    });

    // Question Management
    Route::get('/passages', [Admin\PassageController::class, 'index'])->name('passages.index');
    Route::prefix('questions')->as('questions.')->group(function () {
        Route::get('/', [Admin\QuestionController::class, 'index'])->name('index');
        Route::post('/', [Admin\QuestionController::class, 'store'])->name('store');
        Route::get('/{question}', [Admin\QuestionController::class, 'show'])->name('show');
        Route::get('/{question}/preview', [Admin\QuestionController::class, 'preview'])->name('preview');
        Route::post('/{question}/duplicate', [Admin\QuestionController::class, 'duplicate'])->name('duplicate');
        Route::patch('/{question}', [Admin\QuestionController::class, 'update'])->name('update');
        Route::delete('/{question}', [Admin\QuestionController::class, 'destroy'])->name('destroy');
    });
    Route::get('/skills/{skill}/questions', [Admin\QuestionController::class, 'indexBySkill'])->name('questions.by-skill');
    Route::get('/skills/{skill}/tags', [Admin\QuestionController::class, 'getTagsBySkill'])->name('questions.tags');
    Route::post('/questions/bulk-level', [Admin\QuestionController::class, 'bulkUpdateLevel'])->name('questions.bulk-level');
    Route::post('/media/upload', [Admin\QuestionController::class, 'uploadMedia'])->name('media.upload');

    // Reporting
    Route::prefix('reports')->as('reports.')->group(function () {
        Route::get('/', [Admin\ReportController::class, 'index'])->name('index');
        Route::get('/{attempt}', [Admin\ReportController::class, 'show'])->name('show');
        Route::post('/{attempt}/reset', [Admin\ReportController::class, 'resetAttempt'])->name('reset');
        Route::post('/{attempt}/skills/{skill}/reset', [Admin\ReportController::class, 'resetAttemptSkill'])->name('reset-skill');
    });

    // Manual Grading (Writing & Speaking) — attempt-based
    Route::prefix('grading')->as('grading.')->group(function () {
        Route::get('/', [Admin\ProductiveSkillsController::class, 'index'])->name('index');
        // Attempt-based routes (must come before /{answer} catch-all)
        Route::get('/attempt/{attempt}',   [Admin\ProductiveSkillsController::class, 'showAttempt'])->name('attempt.show');
        Route::patch('/attempt/{attempt}', [Admin\ProductiveSkillsController::class, 'gradeAttempt'])->name('attempt.grade');
        // Legacy single-answer routes
        Route::get('/{answer}',   [Admin\ProductiveSkillsController::class, 'show'])->name('show');
        Route::patch('/{answer}', [Admin\ProductiveSkillsController::class, 'update'])->name('update');
        Route::post('/{answer}/ai-suggest', [Admin\ProductiveSkillsController::class, 'aiSuggest'])->name('ai-suggest');
    });

    // Staff Management (Admin Only)
    Route::middleware(AdminRole::class)->prefix('staff')->as('staff.')->group(function () {
        Route::get('/', [Admin\StaffController::class, 'index'])->name('index');
        Route::post('/', [Admin\StaffController::class, 'store'])->name('store');
        Route::get('/{user}', [Admin\StaffController::class, 'show'])->name('show');
        Route::patch('/{user}', [Admin\StaffController::class, 'update'])->name('update');
        Route::delete('/{user}', [Admin\StaffController::class, 'destroy'])->name('destroy');
    });

    // Resource Management
    Route::apiResource('packages', Admin\PackageController::class)->names('packages');
    Route::apiResource('exam-categories', Admin\ExamCategoryController::class)->names('exam-categories');
    Route::apiResource('system-requirements', Admin\SystemRequirementController::class)->names('system-requirements');

    // Certificates & Templates
    Route::get('/certificates', [CertificateController::class, 'adminIndex'])->name('certificates.index');
    Route::get('/certificate-templates/{template}/preview', [Admin\CertificateTemplateController::class, 'previewPdf'])->name('certificate-templates.preview');
    Route::apiResource('certificate-templates', Admin\CertificateTemplateController::class)->names('certificate-templates');

    // Notifications
    Route::prefix('notifications')->as('notifications.')->group(function () {
        Route::get('/', [Admin\NotificationController::class, 'index'])->name('index');
        Route::post('/mark-as-read', [Admin\NotificationController::class, 'markAsRead'])->name('mark-as-read');
    });

    // Activity Logs
    Route::get('/activity-logs', [Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::post('/activity-logs/bulk-delete', [Admin\ActivityLogController::class, 'bulkDestroy'])->name('activity-logs.bulk-delete');
    Route::get('/activity-logs/{id}', [Admin\ActivityLogController::class, 'show'])->name('activity-logs.show');
    Route::delete('/activity-logs/{id}', [Admin\ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');

    // Proctoring Management
    Route::prefix('proctoring')->as('proctoring.')->group(function () {
        Route::get('/', [Admin\ProctoringController::class, 'index'])->name('index');
        Route::get('/statistics', [Admin\ProctoringController::class, 'statistics'])->name('statistics');
        Route::get('/{session}', [Admin\ProctoringController::class, 'show'])->name('show');
        Route::get('/{session}/violations', [Admin\ProctoringController::class, 'violations'])->name('violations');
        Route::patch('/{session}/status', [Admin\ProctoringController::class, 'updateStatus'])->name('update-status');
        Route::post('/{violation}/review', [Admin\ProctoringController::class, 'reviewViolation'])->name('review-violation');
        Route::get('/{session}/report', [Admin\ProctoringController::class, 'report'])->name('report');
        Route::get('/{session}/export', [Admin\ProctoringController::class, 'exportReport'])->name('export-report');
    });

    // Utilities
    Route::get('/languages', [Admin\LanguageController::class, 'index'])->name('languages.index');
});
