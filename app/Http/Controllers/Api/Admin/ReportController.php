<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSkill;
use App\Models\ExamAttemptLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get reports (For Supervisor and Admin)
     */
    public function index(Request $request)
    {
        $attempts = ExamAttempt::with(['student.user', 'user', 'exam'])
            ->whereIn('status', ['completed', 'ongoing'])
            ->orderBy('updated_at', 'desc')
            ->paginate(30);
        return response()->json($attempts);
    }

    /**
     * Get detailed movement report for a specific attempt
     */
    public function show(ExamAttempt $attempt)
    {
    //  dd("I3m here...............");
        $attempt->load([
            'student.user', 
            'user',
            'exam', 
            'attemptSkills.skill', 
            'attemptLevels' => function($q) {
                $q->orderBy('created_at', 'asc');
            },
            'answers' => function($q) {
                $q->with([
                    'question' => function($sq) {
                        $sq->with(['passage', 'options', 'skill']);
                    },
                    'option'
                ])->orderBy('created_at', 'asc');
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
            DB::beginTransaction();
                // Cascading delete is preferred if relationships are properly set, 
                // but we'll do it explicitly here for safety with student answers.
                \App\Models\StudentAnswer::where('exam_attempt_id', $attempt->id)->delete();
                \App\Models\ExamAttemptSkill::where('exam_attempt_id', $attempt->id)->delete();
                 \App\Models\ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->delete();
                $attempt->delete();
            DB::commit();
            return response()->json(['message' => 'Candidate progress has been successfully reset. They can now retake the assessment.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to reset candidate progress: ' . $e->getMessage()], 500);
        }
    }

    public function resetAttemptٍSkill(Request $request, ExamAttempt $attempt, ExamAttemptSkill $skill)
    {
        try {
            DB::beginTransaction();
                // Cascading delete is preferred if relationships are properly set, 
                // but we'll do it explicitly here for safety with student answers.
               // \App\Models\StudentAnswer::where('exam_attempt_id', $attempt->id)->where('skill_id', $skill->id)->delete();

                \App\Models\ExamAttemptSkill::where('exam_attempt_id', $attempt->id)->where('skill_id', $skill->id)->delete();
                \App\Models\ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->where('skill_id', $skill->id)->delete();
                \App\Models\StudentAnswer::where('exam_attempt_id', $attempt->id)->delete();
              //  $attempt->delete();
            DB::commit();
            return response()->json(['message' => 'Candidate progress has been successfully reset. They can now retake the assessment.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to reset candidate progress: ' . $e->getMessage()], 500);
        }
    }


}
