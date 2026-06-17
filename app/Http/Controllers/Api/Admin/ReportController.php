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
        $skillIds = $currentPos['skill_ids'] ?? [];
   
        $attempt->total_levels = Skill::whereIn('id', $skillIds)
            ->withCount('levels')
            ->get()
            ->sum('levels_count');

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
       // $attempt->skill_ids = $currentPos['skill_ids'] ?? [];

        $skillIds = $currentPos['skill_ids'] ?? [];
   
        $attempt->total_levels = Skill::whereIn('id', $skillIds)
            ->withCount('levels')
            ->get()
            ->sum('levels_count');


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
                if ($resetIndex !== false ) {
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

public function resetAttemptLastLevel(Request $request, ExamAttempt $attempt, int $skill)
{
    try {
        DB::beginTransaction();

        $skillId = (int) $skill;

        // 1. جيب آخر level اتعملت للـ skill دي
        $lastLevel = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->orderBy('level_number', 'desc')
            ->first();

        if (!$lastLevel) {
            return response()->json(['error' => 'No level found to reset for this skill.'], 404);
        }

        // 2. جيب الـ level اللي قبله (لو موجود) عشان نعرف التوقيت
        $previousLevel = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->orderBy('level_number', 'desc')
            ->skip(1)
            ->first();

        // 3. احذف StudentAnswers اللي اتعملت من بداية اللevel دي
        $answersQuery = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId);

        if ($previousLevel) {
            // احذف الأجوبة اللي جات بعد انتهاء الـ level السابق
            $answersQuery->where('created_at', '>', $previousLevel->updated_at);
        } else {
            // لو دي أول level، احذف كل أجوبة الـ skill دي
            $answersQuery->where('created_at', '>=', $lastLevel->created_at);
        }

        $deletedAnswers = $answersQuery->count();
        $answersQuery->delete();

        // 4. احذف الـ ExamAttemptLevel
        $lastLevel->delete();


        // ✅ 4.5 رجّع status الـ skill لـ ongoing عشان الطالب يقدر يكملها
        $attemptSkill = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->first();

        if ($attemptSkill) {
            // احسب الـ score من الـ levels الباقية بعد الحذف
            $remainingAvgScore = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $skillId)
                ->sum('score') ?? 0;

            $attemptSkill->status = 'in_progress';
            $attemptSkill->finished_at = null;
            $attemptSkill->score = $remainingAvgScore;
            $attemptSkill->max_level_reached = max(1, $lastLevel->level_number - 1);
            $attemptSkill->save();
        }

        // 5. رجّع current_position
        $pos = $attempt->current_position ?? [];
        if (is_string($pos)) {
            $pos = json_decode($pos, true);
        }

        $currentLevel = $pos['current_level'] ?? 1;
        $pos['current_level'] = max(1, $currentLevel - 1);

        // لو الـ attempt كان completed، رجّعه ongoing
        if ($attempt->status === 'completed') {
            $attempt->status = 'ongoing';
            $attempt->finished_at = null;

            // شيل الـ skill من completed_skills لو موجودة
            if (isset($pos['completed_skills'])) {
                $pos['completed_skills'] = array_values(
                    array_filter($pos['completed_skills'], fn($id) => $id != $skillId)
                );
            }
        }

        $attempt->current_position = $pos;
        $attempt->save();

        // 6. سجّل الـ activity
        $skillModel = Skill::find($skillId);
        $skillName = $skillModel ? $skillModel->name : "Skill #$skillId";

        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => 'updated',
            'model_type'  => ExamAttempt::class,
            'model_id'    => $attempt->id,
            'description' => "Last level ({$lastLevel->level_number}) of [{$skillName}] reset for candidate: "
                           . ($attempt->student->user->name ?? 'Unknown'),
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);

        DB::commit();

        return response()->json([
            'message'           => 'Last level has been reset successfully. The candidate can retake it.',
            'reset_level'       => $lastLevel->level_number,
            'new_current_level' => $pos['current_level'],
            'deleted_answers'   => $deletedAnswers,
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => 'Failed to reset level: ' . $e->getMessage()], 500);
    }
}

}
