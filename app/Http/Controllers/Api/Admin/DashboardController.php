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
            'live_students_count' => ExamAttempt::where('status', 'ongoing')
                ->where('updated_at', '>=', now()->subMinutes(30))
                ->count(),
            'recent_attempts' => ExamAttempt::with(['student.user', 'exam', 'attemptSkills.skill'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function($attempt) {
                    $totalScore = $attempt->attemptSkills->sum('score');
                    $attempt->total_score = $totalScore;
                    // Assuming max is 900 * number of assigned skills, but let's just show the raw sum for now
                    $attempt->accuracy = $attempt->attemptSkills->avg('score') ?? 0;
                    return $attempt;
                }),
        ]);
    }
}
