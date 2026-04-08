<?php

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
        Route::get('/attempts/{attempt}', function (\App\Models\ExamAttempt $attempt) {
            return $attempt->load(['exam', 'student']);
        });
    });

    // Admin/Teacher/Supervisor Routes
    Route::prefix('admin')->middleware(StaffRole::class)->group(function () {
        Route::get('/stats', [Admin\DashboardController::class, 'stats']);
        
        // Student Management
        Route::get('/students', [Admin\StudentController::class, 'index']);
        Route::get('/students/template', [Admin\StudentController::class, 'downloadTemplate']);
        Route::post('/students', [Admin\StudentController::class, 'store']);
        Route::get('/students/{student}', [Admin\StudentController::class, 'show']);
        Route::patch('/students/{student}', [Admin\StudentController::class, 'update']);
        Route::delete('/students/{student}', [Admin\StudentController::class, 'destroy']);
        Route::post('/students/batch', [Admin\StudentController::class, 'batchImport']);

        // Exam Management
        Route::get('/exams', [Admin\ExamController::class, 'index']);

        // Route::apiResource('/partners', PartnerController::class);

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
        Route::delete('/exams/{exam}', [Admin\ExamController::class, 'destroy']);
        Route::post('/exams/import-folder', [QuestionImportController::class, 'importFolder']);

        // Skill & Level Management
        Route::get('/skills', [Admin\SkillController::class, 'index']);
        Route::post('/skills', [Admin\SkillController::class, 'store']);
        Route::delete('/skills/{skill}', [Admin\SkillController::class, 'destroy']);
        Route::get('/skills-with-levels', [Admin\SkillController::class, 'getSkillsWithLevels']);
        Route::get('/skills/{skill}/levels', [Admin\SkillController::class, 'getSkillWithLevels']);
        Route::patch('/levels/{level}', [Admin\LevelController::class, 'update']);

        // Question Management
        Route::get('/questions', [Admin\QuestionController::class, 'index']);
        Route::post('/questions', [Admin\QuestionController::class, 'store']);
        Route::get('/questions/{question}', [Admin\QuestionController::class, 'show']);
        Route::patch('/questions/{question}', [Admin\QuestionController::class, 'update']);
        Route::delete('/questions/{question}', [Admin\QuestionController::class, 'destroy']);

        // Reports & Attempts
        Route::get('/reports', [Admin\ReportController::class, 'index']);
        Route::post('/attempts/{attempt}/reset', [Admin\ReportController::class, 'resetAttempt']);

        // Staff Management (Admin Only)
        Route::middleware(AdminRole::class)->group(function () {
            Route::get('/staff', [Admin\StaffController::class, 'index']);
            Route::post('/staff', [Admin\StaffController::class, 'store']);
            Route::patch('/staff/{user}', [Admin\StaffController::class, 'update']);
            Route::delete('/staff/{user}', [Admin\StaffController::class, 'destroy']);
        });

        // Utilities
        Route::get('/languages', [Admin\LanguageController::class, 'index']);
        Route::get('/packages', [Admin\PackageController::class, 'index']);
    });

    // Global Question Import (Legacy/Admin)
    Route::post('/questions/import', [QuestionImportController::class, 'import'])->middleware(AdminRole::class);
});

Route::post('/parent/results', [ParentController::class, 'viewResults']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// WordPress Webhook (Public)
Route::post('/webhook/wordpress/student-registration', [\App\Http\Controllers\Api\WordPressWebhookController::class, 'register']);