<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\QuestionImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ParentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        if ($user->role === 'student' && $user->student) {
            return $user->load('student');
        }
        return $user;
    });
    Route::get('/exams', [\App\Http\Controllers\Api\ExamController::class, 'index']);

    // Admin/Teacher/Supervisor Routes
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/students', [AdminController::class, 'students']);
        Route::patch('/students/{student}', [AdminController::class, 'updateStudent']);
        Route::post('/students/batch', [AdminController::class, 'batchImport']);
        Route::post('/students', [AdminController::class, 'storeStudent']);
        Route::get('/exams', [AdminController::class, 'exams']);
        Route::post('/exams', [AdminController::class, 'storeExam']);
        Route::get('/exams/{exam}', [AdminController::class, 'getExam']);
        Route::patch('/exams/{exam}', [AdminController::class, 'updateExam']);
        Route::post('/exams/import-folder', [QuestionImportController::class, 'importFolder']);
        Route::get('/skills', [AdminController::class, 'skills']);
        Route::post('/skills', [AdminController::class, 'storeSkill']);
        Route::delete('/skills/{skill}', [AdminController::class, 'deleteSkill']);
        Route::get('/questions', [AdminController::class, 'questions']);
        Route::post('/questions', [AdminController::class, 'storeQuestion']);
        Route::get('/questions/{question}', [AdminController::class, 'getQuestion']);
        Route::patch('/questions/{question}', [AdminController::class, 'updateQuestion']);
        Route::delete('/questions/{question}', [AdminController::class, 'deleteQuestion']);
        Route::get('/languages', [AdminController::class, 'languages']);
        Route::post('/attempts/{attempt}/reset', [AdminController::class, 'resetAttempt']);
        Route::get('/reports', [AdminController::class, 'reports']);
        
        // Level Management (Phase 11)
        Route::get('/skills-with-levels', [AdminController::class, 'getSkillsWithLevels']);
        Route::get('/skills/{skill}/levels', [AdminController::class, 'getSkillWithLevels']);
        Route::patch('/levels/{level}', [AdminController::class, 'updateLevel']);

        // Staff & Role Management (Phase 22)
        Route::get('/staff', [AdminController::class, 'getStaff']);
        Route::post('/staff', [AdminController::class, 'storeStaff']);
        Route::patch('/staff/{user}', [AdminController::class, 'updateStaff']);
        Route::delete('/staff/{user}', [AdminController::class, 'deleteStaff']);
    });

    // Admin-only (simplified)
    Route::post('/questions/import', [QuestionImportController::class, 'import']);
    
    // Adaptive Exam Engine Routes
    Route::post('/exams/{exam}/start', [\App\Http\Controllers\Api\ExamController::class, 'start']);
    Route::get('/attempts/{attempt}/next-batch', [\App\Http\Controllers\Api\ExamController::class, 'getNextBatch']);
    Route::post('/attempts/{attempt}/submit-batch', [\App\Http\Controllers\Api\ExamController::class, 'submitBatch']);
    Route::get('/attempts/{attempt}', function (\App\Models\ExamAttempt $attempt) {
        return $attempt->load(['exam', 'student']);
    });
});

Route::post('/parent/results', [ParentController::class, 'viewResults']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// WordPress Webhook (Public)
Route::post('/webhook/wordpress/student-registration', [\App\Http\Controllers\Api\WordPressWebhookController::class, 'register']);