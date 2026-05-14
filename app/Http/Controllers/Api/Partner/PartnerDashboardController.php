<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\ExamAttempt;
use Carbon\Carbon;

class PartnerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $partnerId = $request->user()->partner->id ?? null;

        if (!$partnerId) {
            return response()->json(['message' => 'Partner profile not found.'], 404);
        }

        $studentIds = Student::where('partner_id', $partnerId)->pluck('id');

        $totalStudents = $studentIds->count();
        
        $totalAttempts = ExamAttempt::whereIn('student_id', $studentIds)->count();
        $completedAttempts = ExamAttempt::whereIn('student_id', $studentIds)->where('status', 'completed')->count();
        $pendingAttempts = ExamAttempt::whereIn('student_id', $studentIds)->where('status', '!=', 'completed')->count();

        $recentAttempts = ExamAttempt::with(['student:id,user_id,partner_id', 'student.user:id,first_name,last_name', 'exam:id,title'])
            ->whereIn('student_id', $studentIds)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'student_name' => $attempt->student->user ? ($attempt->student->user->first_name . ' ' . $attempt->student->user->last_name) : 'Unknown',
                    'exam_title' => $attempt->exam->title ?? 'Unknown',
                    'status' => $attempt->status,
                    'total_score' => round($attempt->overall_score ?? 0, 1),
                    'avg_score' => round($attempt->overall_score ?? 0, 1), 
                    'created_at' => $attempt->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'data' => [
                'stats' => [
                    'students' => [
                        'total' => $totalStudents,
                        'today' => Student::where('partner_id', $partnerId)->whereDate('created_at', Carbon::today())->count(),
                    ],
                    'exams' => [
                        'total' => ExamAttempt::whereIn('student_id', $studentIds)->distinct('exam_id')->count('exam_id'),
                        'today' => ExamAttempt::whereIn('student_id', $studentIds)->whereDate('created_at', Carbon::today())->distinct('exam_id')->count('exam_id'),
                    ],
                    'attempts' => [
                        'total' => $totalAttempts,
                        'completed' => $completedAttempts,
                        'pending' => $pendingAttempts,
                        'last_7_days' => ExamAttempt::whereIn('student_id', $studentIds)->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
                    ],
                    'live' => ExamAttempt::whereIn('student_id', $studentIds)->where('status', 'ongoing')->count(),
                ],
                'recent_attempts' => $recentAttempts
            ]
        ]);
    }
}
