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
        $limit = $request->get('limit', 5);

        $stats = cache()->remember('admin_dashboard_stats', 30, function () use ($limit) {
            return [
                'students_count' => Student::count(),
                'students_today' => Student::whereDate('created_at', today())->count(),

                'exams_count' => Exam::count(),
                'exams_today' => Exam::whereDate('created_at', today())->count(),

                'attempts_count' => ExamAttempt::count(),
                'attempts_last_7_days' => ExamAttempt::where('created_at', '>=', now()->subDays(7))->count(),

                'live_students_count' => ExamAttempt::where('status', 'ongoing')
                    ->where('updated_at', '>=', now()->subMinutes(10)) // Reduced to 10 mins for better accuracy
                    ->count(),

                'recent_attempts' => ExamAttempt::select(['id', 'student_id', 'user_id', 'exam_id', 'status', 'created_at', 'current_position', 'overall_score'])

                    ->with([
                        'student:id,user_id',
                        'student.user:id,first_name,last_name',
                        'user:id,first_name,last_name',
                        'exam:id,title',
                        'attemptSkills.skill' => function ($query) {
            $query->withCount('levels');}
                    ])
                    ->withSum('attemptSkills', 'score')
                    ->withAvg('attemptSkills', 'score')
                    ->orderBy('created_at', 'desc')
                    ->take($limit)
                    ->get(),
            ];
        });

        return \App\Http\Resources\DashboardResource::make($stats);
    }
}
