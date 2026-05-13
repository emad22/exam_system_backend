<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptLevel;
use App\Models\ExamAttemptSkill;
use App\Services\AttemptService;
use Illuminate\Http\Request;

class ExamSecurityController extends Controller
{
    public function __construct(
        private readonly AttemptService $attemptService,
    ) {}

    public function logWarning(Request $request, ExamAttempt $attempt)
    {
        $this->authorize('update', $attempt);
        if ($attempt->status !== 'ongoing') return response()->json(['error' => 'Exam is not active.'], 403);

        $pos = $attempt->current_position ?? [];
        $currentWarnings = 0; $shouldTerminateSkill = false;

        if (isset($pos['skill_ids'][$pos['current_skill_index']])) {
            $skillId = $pos['skill_ids'][$pos['current_skill_index']];
            $skillAttempt = ExamAttemptSkill::firstOrCreate(
                ['exam_attempt_id' => $attempt->id, 'skill_id' => $skillId], 
                ['started_at' => now(), 'status' => 'in_progress']
            );
            $skillAttempt->increment('cheat_warnings');
            $currentWarnings = $skillAttempt->cheat_warnings;

            if ($currentWarnings >= 3) {
                // We no longer auto-terminate the skill. 
                // We just record the warnings and let the admin see them in the report.
                // $shouldTerminateSkill = true;
            }
        }
        return response()->json(['success' => true, 'warnings' => $currentWarnings, 'should_terminate_skill' => false]);
    }

    public function timeout(Request $request, ExamAttempt $attempt)
    {
        $this->authorize('update', $attempt);
        
        $skillId = $request->input('skill_id') ?? ($attempt->current_position['skill_ids'][$attempt->current_position['current_skill_index']] ?? null);

        if (!$skillId) {
            return response()->json(['success' => true, 'message' => 'No active skill to timeout.']);
        }

        // Find the specific skill attempt and depend on its status
        $skillAttempt = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->first();

        if ($skillAttempt && $skillAttempt->status === 'in_progress') {
            $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
            $maxLevel = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $skillId)
                ->max('level_number') ?? 1;

            $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $maxLevel, 'completed');
            $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);
            
            $pos = $attempt->current_position ?? [];
            $advanced = $this->attemptService->advanceToNextSkillOrFinish($attempt, $pos, $skillId);
            $attempt->update(['current_position' => $advanced['next_pos']]);

            if ($advanced['finished_exam']) {
                $this->attemptService->completeAttempt($attempt);
            }
        }

        return response()->json(['success' => true, 'next_step' => 'dashboard']);
    }
}
