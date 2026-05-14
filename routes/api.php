<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\Admin\SystemRequirementController;
use App\Http\Controllers\Api\Admin\ExamCategoryController;
use App\Http\Controllers\Api\WordPressWebhookController;
use App\Http\Controllers\Api\QuestionImportController;
use App\Http\Middleware\AdminRole;
use App\Http\Middleware\PartnerRole;

/*
|--------------------------------------------------------------------------
| API Routes Structure
|--------------------------------------------------------------------------
| The routes are split into modular files for better maintainability:
| - auth.php: Login, Register, Profile
| - admin.php: All administrative and staff routes
| - student.php: Exam taking, results, and student certificates
*/

// Wrap everything in v1
Route::prefix('v1')->as('v1.')->group(function () {
    // 1. Authentication & Profile
    require __DIR__ . '/auth.php';

    // 2. Student & Exam Operations
    require __DIR__ . '/student.php';

    // 3. Administrative Operations
    require __DIR__ . '/admin.php';

    // 4. Partner Operations
    require __DIR__ . '/partner.php';

    // ---------------------------------------------------------------------------
    // 5. Shared / Public Routes
    // ---------------------------------------------------------------------------

    // Public Certificate Verification (No Auth Required)
    Route::get('/verify-certificate/{code}', [CertificateController::class, 'verify'])->name('certificates.verify');

    // Public Requirements & Categories
    Route::get('/public/system-requirements', [SystemRequirementController::class, 'activeList'])->name('public.requirements');
    Route::get('/public/exam-categories', [ExamCategoryController::class, 'index'])->name('public.categories');

    // External Integrations
    Route::post('/webhook/wordpress/student-registration', [WordPressWebhookController::class, 'register'])->name('webhooks.wordpress');

    // Legacy / Global Admin Tools
    Route::middleware(['auth:sanctum', AdminRole::class])->group(function () {
        Route::post('/questions/import', [QuestionImportController::class, 'import'])->name('admin.questions.legacy-import');
    });
});