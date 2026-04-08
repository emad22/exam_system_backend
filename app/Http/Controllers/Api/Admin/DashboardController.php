<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Student;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        return response()->json([
            'students_count' => Student::count(),
            'exams_count' => Exam::count(),
            'attempts_count' => ExamAttempt::count(),
            'recent_attempts' => ExamAttempt::with(['student', 'exam'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(),
        ]);
    }
}
