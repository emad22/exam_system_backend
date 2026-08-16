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

    /**
     * Get list of students currently taking exams live
     */
    public function liveStudents(Request $request)
    {
        try {
            $liveStudents = ExamAttempt::where('status', 'ongoing')
                ->where('updated_at', '>=', now()->subMinutes(10))
                ->select(['id', 'student_id', 'exam_id', 'status', 'created_at', 'updated_at'])
                ->with([
                    'student' => function($query) {
                        $query->select(['id', 'user_id']);
                    },
                    'student.user' => function($query) {
                        $query->select(['id', 'first_name', 'last_name', 'email']);
                    },
                    'exam' => function($query) {
                        $query->select(['id', 'title']);
                    }
                ])
                ->orderBy('updated_at', 'desc')
                ->get();

            $formattedStudents = $liveStudents->map(function($attempt) {
                $firstName = $attempt->student?->user?->first_name ?? '';
                $lastName = $attempt->student?->user?->last_name ?? '';
                $studentName = trim($firstName . ' ' . $lastName) ?: 'Unknown Student';
                $studentEmail = $attempt->student?->user?->email ?? 'N/A';
                $examTitle = $attempt->exam?->title ?? 'Unknown Exam';
                $createdAt = $attempt->created_at;
                $now = now();
                $durationMinutes = $createdAt ? $createdAt->diffInMinutes($now) : 0;

                return [
                    'id' => $attempt->id,
                    'student_id' => $attempt->student_id,
                    'student_name' => $studentName,
                    'student_email' => $studentEmail,
                    'exam_title' => $examTitle,
                    'exam_level' => 'N/A',
                    'started_at' => $createdAt,
                    'last_activity' => $attempt->updated_at,
                    'duration_minutes' => $durationMinutes,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedStudents,
                'total' => $formattedStudents->count(),
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching live students: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'total' => 0
            ], 500);
        }
    }
}
