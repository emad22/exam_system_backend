<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\Student;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    /**
     * Parent logs in with unique code to see student results
     */
    public function viewResults(Request $request)
    {
        $request->validate([
            'parent_code' => 'required|string|exists:students,parent_code'
        ]);

        $student = Student::where('parent_code', $request->parent_code)->firstOrFail();

        $attempts = ExamAttempt::with(['exam'])
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'student_name' => $student->first_name . ' ' . $student->last_name,
            'student_level' => $student->language_level,
            'attempts' => $attempts
        ]);
    }
}
