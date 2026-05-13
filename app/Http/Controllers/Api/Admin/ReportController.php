<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSkill;
use App\Models\ExamAttemptLevel;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Models\Skill;

class ReportController extends Controller
{
    /**
     * Get reports (For Supervisor and Admin)
     */
   

     public function index(Request $request)
    {
        $attempts = ExamAttempt::with(['student.user', 'user', 'exam', 'attemptSkills.skill' => function ($query) {
            $query->withCount('levels');
        }])
            ->whereIn('status', ['completed', 'ongoing'])
            ->orderBy('updated_at', 'desc')
            ->paginate(30);
//to get avialable skills for each ExamAttemp

        $attempts->getCollection()->transform(function ($attempt) {
        $currentPos = $attempt-> current_position;
       //logger("*************** current position ".json_encode($attempt->current_pos));
        if (is_string($currentPos)) {
            $currentPos = json_decode($currentPos, true);
        }
        $attempt->skills_count = count($currentPos['skill_ids'] ?? []);
        return $attempt;
     });
        return response()->json($attempts);
    }

   

    /**
     * Get detailed movement report for a specific attempt
     */
    public function show(ExamAttempt $attempt)
    {

        $attempt->load([
            'student.user',
            'user',
            'exam',
            'attemptSkills.skill' => function ($q) {
                $q->withCount('levels');
            },
            'attemptLevels' => function ($q) {
                $q->orderBy('created_at', 'asc');
            },
            'answers' => function ($q) {
                $q->with([
                    'question' => function ($sq) {
                        $sq->with(['passage', 'options', 'skill']);
                    },
                    'option'
                ])->orderBy('created_at', 'asc');
            }
        ]);

   
        $currentPos = $attempt-> current_position;
       //logger("*************** current position ".json_encode($attempt->current_pos));   
        if (is_string($currentPos)) {
            $currentPos = json_decode($currentPos, true);
        }
        $attempt->skills_count = count($currentPos['skill_ids'] ?? []);
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
            StudentAnswer::where('exam_attempt_id', $attempt->id)->delete();
            ExamAttemptSkill::where('exam_attempt_id', $attempt->id)->delete();
            ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->delete();

            // Log the activity before deletion
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'deleted',
                'model_type' => ExamAttempt::class,
                'model_id' => $attempt->id,
                'description' => "Full exam attempt reset for candidate: " . ($attempt->student->user->name ?? 'Unknown'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $attempt->delete();
            DB::commit();
            return response()->json(['message' => 'Candidate progress has been successfully reset. They can now retake the assessment.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to reset candidate progress: ' . $e->getMessage()], 500);
        }
    }

    public function resetAttemptSkill(Request $request, ExamAttempt $attempt, $skill)
    {
        try {
            DB::beginTransaction();

            $skillId = (int) $skill;

            if ($attempt->status == "completed") {
                $attempt->status = "ongoing";
                $attempt->finished_at = null;
            }

            // 1. Update current_position to remove from completed_skills and rewind index
            $pos = $attempt->current_position ?? [];
            if (isset($pos['completed_skills'])) {
                $pos['completed_skills'] = array_values(array_filter($pos['completed_skills'], fn($id) => $id != $skillId));
            }

            // Find the index of the reset skill to rewind the student to it
            if (isset($pos['skill_ids'])) {
                $resetIndex = array_search($skillId, $pos['skill_ids']);
                if ($resetIndex !== false && (!isset($pos['current_skill_index']) || $resetIndex < $pos['current_skill_index'])) {
                    $pos['current_skill_index'] = $resetIndex;
                    $pos['current_level'] = 1;
                    $pos['current_skill_started_at'] = null;
                }
            }

            $attempt->current_position = $pos;
            $attempt->save();

            ExamAttemptSkill::where('exam_attempt_id', $attempt->id)->where('skill_id', $skillId)->delete();
            ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->where('skill_id', $skillId)->delete();
            StudentAnswer::where('exam_attempt_id', $attempt->id)->where('skill_id', $skillId)->delete();
            $overall = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
                ->whereHas('skill', function ($q) {
                    $q->where(function ($query) {
                        $query->where('name', 'like', '%read%')
                            ->orWhere('name', 'like', '%listen%')
                            ->orWhere('name', 'like', '%struct%');
                    });
                })
                ->avg('score') ?? 0;
            logger("********************************in report" . $overall);
            $attempt->update([
                'overall_score' => $overall
            ]);

            // Log the skill reset activity
            $skillModel = Skill::find($skillId);
            $skillName = $skillModel ? $skillModel->name : "Skill #$skillId";

            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'updated',
                'model_type' => ExamAttempt::class,
                'model_id' => $attempt->id,
                'description' => "Skill [{$skillName}] reset for candidate: " . ($attempt->student->user->name ?? 'Unknown'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();
            return response()->json(['message' => 'Skill progress has been successfully reset. The candidate can now retake this skill.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to reset skill progress: ' . $e->getMessage()], 500);
        }
    }


}
