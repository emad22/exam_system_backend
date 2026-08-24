<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parent\ViewResultsRequest;
use App\Models\ExamAttempt;
use App\Models\Student;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    /**
     * Parent logs in with unique code to see student results
     */
    public function viewResults(ViewResultsRequest $request)
    {
        $validated = $request->validated();

        $student = Student::where('parent_code', $validated['parent_code'])->firstOrFail();

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
