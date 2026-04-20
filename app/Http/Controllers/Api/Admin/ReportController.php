<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Get reports (For Supervisor and Admin)
     */
    public function index(Request $request)
    {
        $attempts = ExamAttempt::with(['student.user', 'exam'])
            ->where('status', 'completed')
            ->orderBy('finished_at', 'desc')
            ->paginate(15);
        return response()->json($attempts);
    }

    /**
     * Get detailed movement report for a specific attempt
     */
    public function show(ExamAttempt $attempt)
    {
        $attempt->load([
            'student.user', 
            'exam', 
            'attemptSkills.skill', 
            'attemptLevels' => function($q) {
                $q->orderBy('created_at', 'asc');
            }
        ]);
        
        return response()->json($attempt);
    }

    /**
     * Reset a specific exam attempt (Void it) so student can retake
     */
    public function resetAttempt(Request $request, ExamAttempt $attempt)
    {
        try {
            $attempt->update(['status' => 'voided']);
            return response()->json(['message' => 'Exam attempt voided successfully. Student can now retake the exam.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to void attempt: ' . $e->getMessage()], 500);
        }
    }
}
