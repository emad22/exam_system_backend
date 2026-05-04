<?php

use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\QuestionImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Admin\PartnerController;
use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\ParentController;
use App\Http\Middleware\AdminRole;
use App\Http\Middleware\StaffRole;
use App\Http\Middleware\StudentOrDemoRole;

use App\Http\Controllers\Api\WordPressWebhookController;



Route::middleware('auth:sanctum')->group(function () {
    // Current User Info
    Route::get('/user', function (Request $request) {
        return $request->user()->load('student');
    });

    // Student Routes
    Route::middleware(StudentOrDemoRole::class)->group(function () {
        Route::get('/exams', [\App\Http\Controllers\Api\ExamController::class, 'index']);
        Route::post('/exams/{exam}/start', [\App\Http\Controllers\Api\ExamController::class, 'start']);
        Route::get('/attempts/{attempt}/next-batch', [\App\Http\Controllers\Api\ExamController::class, 'getNextBatch']);
        Route::post('/attempts/{attempt}/submit-batch', [\App\Http\Controllers\Api\ExamController::class, 'submitBatch']);
        Route::post('/attempts/{attempt}/timeout', [\App\Http\Controllers\Api\ExamController::class, 'timeout']);
        Route::post('/attempts/{attempt}/finish', [\App\Http\Controllers\Api\ExamController::class, 'finish']);
        Route::post('/attempts/{attempt}/update-progress', [\App\Http\Controllers\Api\ExamController::class, 'updateProgress']);
        Route::get('/attempts/{attempt}', function (\App\Models\ExamAttempt $attempt) {
            return $attempt->load(['exam', 'student']);
        });
        Route::get('/attempts/{attempt}/results', [\App\Http\Controllers\Api\ExamController::class, 'results']);
        Route::post('/attempts/{attempt}/log-warning', [\App\Http\Controllers\Api\ExamController::class, 'logWarning']);
        Route::post('/exams/{exam}/reset-demo', [\App\Http\Controllers\Api\ExamController::class, 'resetDemo']);
    });

    // Admin/Teacher/Supervisor Routes
    Route::prefix('admin')->middleware(StaffRole::class)->group(function () {
        Route::get('/stats', [Admin\DashboardController::class, 'stats']);

        // Student Management
        Route::get('/students', [Admin\StudentController::class, 'index']);
        Route::get('/students/template', [Admin\StudentController::class, 'downloadTemplate']);
        Route::post('/students/bulk-delete', [Admin\StudentController::class, 'bulkDestroy']);
        Route::post('/students/bulk-skills', [Admin\StudentController::class, 'bulkUpdateSkills']);
        Route::get('/students/bulk-skills-export', [Admin\StudentController::class, 'exportSkillsExcel']);
        Route::post('/students/bulk-skills-import', [Admin\StudentController::class, 'importSkillsExcel']);
        Route::post('/students/batch', [Admin\StudentController::class, 'batchImport']);
        Route::post('/students', [Admin\StudentController::class, 'store']);
        Route::get('/students/{student}', [Admin\StudentController::class, 'show']);
        Route::patch('/students/{student}', [Admin\StudentController::class, 'update']);
        Route::delete('/students/{student}', [Admin\StudentController::class, 'destroy']);
        Route::post('/students/{student}/reset', [Admin\StudentController::class, 'resetExamAttempts']);

        // Exam Management
        Route::get('/exams', [Admin\ExamController::class, 'index']);

        // partner Management
        Route::get('/partners', [PartnerController::class, 'index']);
        Route::post('/partners', [PartnerController::class, 'store']);
        Route::get('/partners/active', [PartnerController::class, 'getActivePartners']);
        Route::get('/partners/{partner}', [PartnerController::class, 'show']);
        Route::patch('/partners/{partner}', [PartnerController::class, 'update']);
        Route::delete('/partners/{partner}', [PartnerController::class, 'destroy']);
        Route::post('/partners/{partner}/hold', [PartnerController::class, 'deactivatePartnerStudents']);
        Route::post('/partners/{partner}/unhold', [PartnerController::class, 'unholdPartner']);



        Route::post('/exams', [Admin\ExamController::class, 'store']);
        Route::get('/exams/{exam}', [Admin\ExamController::class, 'show']);
        Route::patch('/exams/{exam}', [Admin\ExamController::class, 'update']);
        Route::patch('/exams/{exam}/set-default', [Admin\ExamController::class, 'setDefault']);
        Route::delete('/exams/{exam}', [Admin\ExamController::class, 'destroy']);
        Route::post('/exams/import-folder', [QuestionImportController::class, 'importFolder']);

        // Skill & Level Management
        Route::get('/skills', [Admin\SkillController::class, 'index']);
        Route::post('/skills', [Admin\SkillController::class, 'store']);
        Route::patch('/skills/{skill}', [Admin\SkillController::class, 'update']);
        Route::delete('/skills/{skill}', [Admin\SkillController::class, 'destroy']);
        Route::get('/skills-with-levels', [Admin\SkillController::class, 'getSkillsWithLevels']);
        Route::get('/skills/{skill}/levels', [Admin\SkillController::class, 'getSkillWithLevels']);
        Route::post('/skills/{skill}/levels/bulk', [Admin\SkillController::class, 'bulkUpdateLevels']);
        Route::get('/levels', [Admin\LevelController::class, 'index']);
        Route::get('/levels/{level}', [Admin\LevelController::class, 'show']);
        Route::post('/levels', [Admin\LevelController::class, 'store']);
        Route::patch('/levels/{level}', [Admin\LevelController::class, 'update']);
        Route::delete('/levels/{level}', [Admin\LevelController::class, 'destroy']);

        // Question Management
        Route::get('/passages', [Admin\PassageController::class, 'index']);
        Route::get('/questions', [Admin\QuestionController::class, 'index']);
        Route::post('/questions', [Admin\QuestionController::class, 'store']);
        Route::get('/questions/{question}', [Admin\QuestionController::class, 'show']);
        Route::patch('/questions/{question}', [Admin\QuestionController::class, 'update']);
        Route::delete('/questions/{question}', [Admin\QuestionController::class, 'destroy']);
        Route::get('/skills/{skill}/questions', [Admin\QuestionController::class, 'indexBySkill']);
        Route::get('/skills/{skill}/tags', [Admin\QuestionController::class, 'getTagsBySkill']);
        Route::post('/questions/bulk-level', [Admin\QuestionController::class, 'bulkUpdateLevel']);
        Route::post('/media/upload', [Admin\QuestionController::class, 'uploadMedia']);

        Route::post('/reports/{attempt}/reset', [ReportController::class, 'resetAttempt']);
        Route::post('/reports/{attempt}/skills/{skill}/reset', [ReportController::class, 'resetAttemptSkill']);
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/{attempt}', [ReportController::class, 'show']);




        // Staff Management (Admin Only)
        Route::middleware(AdminRole::class)->group(function () {
            Route::get('/staff', [Admin\StaffController::class, 'index']);
            Route::post('/staff', [Admin\StaffController::class, 'store']);
            Route::get('/staff/{user}', [Admin\StaffController::class, 'show']);
            Route::patch('/staff/{user}', [Admin\StaffController::class, 'update']);
            Route::delete('/staff/{user}', [Admin\StaffController::class, 'destroy']);
        });

        // Utilities
        Route::get('/languages', [Admin\LanguageController::class, 'index']);

        // Package Management
        Route::get('/packages', [Admin\PackageController::class, 'index']);
        Route::post('/packages', [Admin\PackageController::class, 'store']);
        Route::get('/packages/{package}', [Admin\PackageController::class, 'show']);
        Route::patch('/packages/{package}', [Admin\PackageController::class, 'update']);
        Route::delete('/packages/{package}', [Admin\PackageController::class, 'destroy']);

        // Exam Categories Management
        Route::get('/exam-categories', [Admin\ExamCategoryController::class, 'index']);
        Route::post('/exam-categories', [Admin\ExamCategoryController::class, 'store']);
        Route::patch('/exam-categories/{category}', [Admin\ExamCategoryController::class, 'update']);
        Route::delete('/exam-categories/{category}', [Admin\ExamCategoryController::class, 'destroy']);

        // System Requirements
        Route::apiResource('system-requirements', Admin\SystemRequirementController::class);

        // Notifications
        Route::get('/notifications', [Admin\NotificationController::class, 'index']);
        Route::post('/notifications/mark-as-read', [Admin\NotificationController::class, 'markAsRead']);
    });

    // Student Fetch Requirements
    Route::get('/public/system-requirements', [Admin\SystemRequirementController::class, 'activeList']);

    // Global Question Import (Legacy/Admin)
    Route::post('/questions/import', [QuestionImportController::class, 'import'])->middleware(AdminRole::class);
});

Route::get('/public/exam-categories', [Admin\ExamCategoryController::class, 'index']);
Route::post('/parent/results', [ParentController::class, 'viewResults']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// WordPress Webhook (Public)
Route::post('/webhook/wordpress/student-registration', [WordPressWebhookController::class, 'register']);