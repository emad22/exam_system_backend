<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptLevel;
use App\Models\Level;
use App\Models\Question;
use App\Models\StudentAnswer;

class AttemptService
{
    /**
     * Compute the aggregate score (percentage) for a skill across all levels.
     */
    public function computeSkillScore(ExamAttempt $attempt, int $skillId): float
    {
        $totalSkillPoints = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->groupBy('level_number')
            ->selectRaw('max(score) as max_score')
            ->get()
            ->sum('max_score');

        $levelCount = Level::where('skill_id', $skillId)->count();

        $levelCount      = max($levelCount, 1); // Avoid division by zero
        $maxPossible     = $levelCount * 100;

        return round(($totalSkillPoints / $maxPossible) * 100, 2);
    }

    /**
     * Recompute and persist the overall_score on the attempt
     * as the average of all skill percentages (including the one just finished).
     */
    public function updateOverallScore(ExamAttempt $attempt, int $skillId, float $currentSkillScore): void
    {
        $coreSkillNames = ['listening', 'reading', 'gramar', 'grammar'];
        $coreSkillIds = \App\Models\Skill::whereIn(\DB::raw('LOWER(name)'), $coreSkillNames)->pluck('id')->toArray();
        
        $coreScores = $attempt->attemptSkills()
            ->whereIn('skill_id', $coreSkillIds)
            ->pluck('score')
            ->toArray();
        
        $assignedSkillIds = $attempt->current_position['skill_ids'] ?? [];
        if (empty($assignedSkillIds)) {
            $assignedSkillIds = $attempt->exam->skills()->pluck('skills.id')->toArray();
        }
        
        $assignedCoreSkillsCount = 0;
        foreach ($assignedSkillIds as $id) {
            if (in_array($id, $coreSkillIds)) {
                $assignedCoreSkillsCount++;
            }
        }
        $assignedCoreSkillsCount = max($assignedCoreSkillsCount, 1);

        $overall = round(array_sum($coreScores) / $assignedCoreSkillsCount, 2);

        $attempt->update(['overall_score' => $overall]);
    }

    /**
     * Record or update the ExamAttemptLevel entry when a student finishes a level.
     */
    public function logLevelResult(ExamAttempt $attempt, int $skillId, Level $level, float $score, float $passThreshold): void
    {
        ExamAttemptLevel::updateOrCreate(
            [
                'exam_attempt_id' => $attempt->id,
                'skill_id'        => $skillId,
                'level_number'    => $level->level_number,
            ],
            [
                'score'  => $score,
                'status' => $score >= $passThreshold ? 'passed' : 'failed',
            ]
        );
    }

    /**
     * Compute the cumulative score for the CURRENT level (all batches answered so far).
     */
    public function computeLevelScore(ExamAttempt $attempt, int $skillId, Level $level): float
    {
        $totalPossible = Question::where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->sum('points');

        $earned = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereIn('question_id', function ($q) use ($attempt, $skillId, $level) {
                $q->select('id')->from('questions')
                  ->where('exam_id', $attempt->exam_id)
                  ->where('skill_id', $skillId)
                  ->where('level_id', $level->id);
            })
            ->sum('points_awarded');

        return $totalPossible > 0 ? round(($earned / $totalPossible) * 100, 2) : 0;
    }

    /**
     * Determine how many questions remain unanswered for this level.
     */
    public function countRemainingQuestions(ExamAttempt $attempt, int $skillId, Level $level): int
    {
        $answeredIds = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->pluck('question_id')
            ->toArray();

        return Question::where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->whereNotIn('id', $answeredIds)
            ->count();
    }

    /**
     * Finalize a skill (mark it completed or failed) and record the result.
     */
    public function finalizeSkill(
        ExamAttempt $attempt,
        int $skillId,
        float $skillScore,
        int $maxLevelReached,
        string $status,
        ?int $placementLevel = null
    ): void {
        $attempt->attemptSkills()->updateOrCreate(
            ['skill_id' => $skillId],
            [
                'max_level_reached' => $maxLevelReached,
                'score'             => $skillScore,
                'status'            => $status,
                'placement_level'   => $placementLevel ?? $maxLevelReached,
                'placement_score'   => $skillScore,
                'finished_at'       => now(),
            ]
        );
    }

    /**
     * Advance the attempt's current_position to the next skill,
     * or mark the exam as finished if no more skills remain.
     *
     * @return array{next_pos: array, finished_exam: bool}
     */
    public function advanceToNextSkillOrFinish(ExamAttempt $attempt, array $pos, int $completedSkillId): array
    {
        $nextPos = $pos;

        // Ensure completed_skills is an array
        $nextPos['completed_skills'] = $nextPos['completed_skills'] ?? [];
        if (!in_array($completedSkillId, $nextPos['completed_skills'])) {
            $nextPos['completed_skills'][] = $completedSkillId;
        }

        if ($pos['current_skill_index'] < count($pos['skill_ids']) - 1) {
            $nextPos['current_skill_index']++;
            $nextPos['current_level']             = 1;
            $nextPos['current_skill_started_at']  = null;
            $finishedExam = false;
        } else {
            $finishedExam = true;
        }

        return ['next_pos' => $nextPos, 'finished_exam' => $finishedExam];
    }

    /**
     * Check if the immediately next level exists AND has questions for this exam.
     */
    public function nextLevelExists(int $examId, int $skillId, int $currentLevelNum): bool
    {
        return Level::where('skill_id', $skillId)
            ->where('level_number', $currentLevelNum + 1)
            ->where('is_active', true)
            ->whereHas('questions', fn($q) => $q->where('exam_id', $examId))
            ->exists();
    }

    /**
     * Check whether a student previously failed this level (for retry detection).
     */
    public function hasPreviousFailure(ExamAttempt $attempt, int $skillId, int $levelNum): bool
    {
        return ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->where('level_number', $levelNum)
            ->where('status', 'failed')
            ->exists();
    }
}
