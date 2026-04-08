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
        $attempts = ExamAttempt::with(['student', 'exam'])
            ->where('status', 'completed')
            ->paginate(20);
        return response()->json($attempts);
    }

    /**
     * Reset a specific exam attempt (Void it) so student can retake
     */
    public function resetAttempt(Request $request, ExamAttempt $attempt)
    {
        $attempt->update(['status' => 'voided']);
        
        return response()->json(['message' => 'Exam attempt voided successfully. Student can now retake the exam.']);
    }
}
