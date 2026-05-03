<?php

namespace App\Services;

use App\Models\ExamQuestionRule;
use App\Models\Level;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Support\Collection;

class QuestionService
{
    /**
     * Fetch the full question batch for the current attempt position.
     * Applies exam rules (standalone / passage / legacy) and deduplicates.
     *
     * @return Collection<Question>
     */
    public function fetchBatchForLevel(int $examId, int $attemptId, int $skillId, Level $level): Collection
    {
        $rules = $this->resolveRules($examId, $skillId, $level->id);

        if ($rules->isEmpty()) {
            return $this->fetchLegacyBatch($examId, $attemptId, $skillId, $level, 0);
        }

        $questions = collect();

        foreach ($rules as $rule) {
            $sQty = $rule->standalone_quantity ?? 0;
            $pQty = $rule->passage_quantity    ?? 0;
            $lQty = $rule->quantity            ?? 0;

            if ($sQty == 0 && $pQty == 0 && $lQty == 0) {
                $questions = $questions->concat($this->fetchLegacyBatch($examId, $attemptId, $skillId, $level, 0));
                continue;
            }

            if ($sQty > 0) {
                $questions = $questions->concat($this->fetchStandaloneBatch($examId, $attemptId, $skillId, $level, $sQty));
            }
            if ($pQty > 0) {
                $questions = $questions->concat($this->fetchPassageBatch($examId, $attemptId, $skillId, $level, $pQty));
            }
            if ($lQty > 0) {
                $questions = $questions->concat($this->fetchLegacyBatch($examId, $attemptId, $skillId, $level, $lQty));
            }
        }

        return $questions->unique('id')->values();
    }

    /**
     * Calculate the total number of questions expected for a level in an exam.
     */
    public function getTotalLevelQuestions(int $examId, int $skillId, int $levelId): int
    {
        $rules = $this->resolveRules($examId, $skillId, $levelId);

        if ($rules->isEmpty()) {
            return Question::where('exam_id', $examId)
                ->where('skill_id', $skillId)
                ->where('level_id', $levelId)
                ->count();
        }

        $total = 0;

        // Pre-compute counts once — avoids repeated queries inside the loop
        $standaloneCount = Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->where('level_id', $levelId)
            ->whereNull('passage_id')
            ->count();

        $passageBaseQuery = Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->where('level_id', $levelId)
            ->whereNotNull('passage_id')
            ->select('passage_id')
            ->distinct();

        $allCount = Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->where('level_id', $levelId)
            ->count();

        foreach ($rules as $rule) {
            $sQty = $rule->standalone_quantity ?? 0;
            $pQty = $rule->passage_quantity    ?? 0;
            $lQty = $rule->quantity            ?? 0;

            if ($sQty > 0) {
                $total += min($sQty, $standaloneCount);
            }

            if ($pQty > 0) {
                // Only pluck exactly the amount we need (e.g., 2 IDs). Almost 0 memory.
                $selectedIds = (clone $passageBaseQuery)->take($pQty)->pluck('passage_id');

                if ($selectedIds->isNotEmpty()) {
                    $total += Question::where('exam_id', $examId)
                        ->whereIn('passage_id', $selectedIds)
                        ->count();
                }
            }

            if ($lQty > 0 && $sQty === 0 && $pQty === 0) {
                $total += min($lQty, $allCount);
            }
        }

        return max($total, 0);
    }

    /**
     * Determine a valid starting level number for a skill.
     * Falls back to the first level that has questions if the requested one is empty.
     */
    public function getValidStartingLevel(int $examId, int $skillId, int $requestedLevelNumber): int
    {
        $requestedLevel = Level::where('skill_id', $skillId)
            ->where('level_number', $requestedLevelNumber)
            ->first();

        if ($requestedLevel) {
            $hasQuestions = Question::where('exam_id', $examId)
                ->where('skill_id', $skillId)
                ->where('level_id', $requestedLevel->id)
                ->exists();

            if ($hasQuestions) {
                return $requestedLevelNumber;
            }
        }

        $firstValid = Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->join('levels', 'levels.id', '=', 'questions.level_id')
            ->orderBy('levels.level_number', 'asc')
            ->value('levels.level_number');

        return $firstValid ?: $requestedLevelNumber;
    }

    // ─── Private query helpers ────────────────────────────────────────────────

    private function resolveRules(int $examId, int $skillId, int $levelId)
    {
        $rules = ExamQuestionRule::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->where('level_id', $levelId)
            ->get();

        if ($rules->isEmpty()) {
            $rules = ExamQuestionRule::where('exam_id', $examId)
                ->where('skill_id', $skillId)
                ->whereNull('level_id')
                ->get();
        }

        return $rules;
    }

    private function getBaseUnansweredQuery(int $examId, int $attemptId, int $skillId, Level $level, bool $ignoreRandom = false)
    {
        $query = Question::where('questions.exam_id', $examId)
            ->where('questions.skill_id', $skillId)
            ->where('questions.level_id', $level->id)
            ->leftJoin('student_answers', function ($join) use ($attemptId) {
                $join->on('questions.id', '=', 'student_answers.question_id')
                     ->where('student_answers.exam_attempt_id', '=', $attemptId);
            })
            ->whereNull('student_answers.id')
            ->select('questions.*');

        return ($level->is_random && !$ignoreRandom)
            ? $query->inRandomOrder()
            : $query->orderBy('questions.sort_order', 'asc')->orderBy('questions.id', 'asc');
    }

    private function fetchStandaloneBatch(int $examId, int $attemptId, int $skillId, Level $level, int $qty): Collection
    {
        $query = $this->getBaseUnansweredQuery($examId, $attemptId, $skillId, $level)
            ->whereNull('passage_id')
            ->with('options');

        return $qty > 0 ? $query->take($qty)->get() : $query->get();
    }

    private function fetchPassageBatch(int $examId, int $attemptId, int $skillId, Level $level, int $qty): Collection
    {
        // DB Subquery: No PHP memory array needed
        $answeredPassagesSubquery = StudentAnswer::where('exam_attempt_id', $attemptId)
            ->join('questions', 'questions.id', '=', 'student_answers.question_id')
            ->whereNotNull('questions.passage_id')
            ->select('questions.passage_id');

        $passageQuery = Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->whereNotNull('passage_id')
            ->whereNotIn('passage_id', $answeredPassagesSubquery)
            ->select('questions.passage_id')
            ->distinct();

        $passageQuery = $level->is_random
            ? $passageQuery->inRandomOrder()
            : $passageQuery->orderBy('questions.passage_id', 'asc');

        $finalQuery = $this->getBaseUnansweredQuery($examId, $attemptId, $skillId, $level, true)
            ->with(['options', 'passage']);

        if ($qty > 0) {
            $ids = $passageQuery->take($qty)->pluck('passage_id');
            
            if ($ids->isEmpty()) {
                return collect();
            }

            $finalQuery->whereIn('passage_id', $ids);
        } else {
            // Unlimited: Pure Subquery! Avoids plucking thousands of IDs.
            $finalQuery->whereIn('passage_id', $passageQuery);
        }

        return $finalQuery->get();
    }

    private function fetchLegacyBatch(int $examId, int $attemptId, int $skillId, Level $level, int $qty): Collection
    {
        $allQuestions = $this->getBaseUnansweredQuery($examId, $attemptId, $skillId, $level)
            ->with(['options', 'passage'])
            ->get();

        $passages = [];
        foreach ($allQuestions as $q) {
            if ($q->passage_id) {
                if (!isset($passages[$q->passage_id])) {
                    $passages[$q->passage_id] = collect();
                }
                $passages[$q->passage_id]->push($q);
            }
        }

        $final = collect();
        $seenPassages = [];
        $remaining = $qty > 0 ? $qty : PHP_INT_MAX;

        foreach ($allQuestions as $q) {
            if ($remaining <= 0) break;

            if ($q->passage_id) {
                if (in_array($q->passage_id, $seenPassages)) {
                    continue;
                }

                $passageQuestions = $passages[$q->passage_id];
                $pCount = $passageQuestions->count();

                // Only include the passage if ALL its questions fit within the limit
                if ($pCount <= $remaining) {
                    $seenPassages[] = $q->passage_id;
                    $final = $final->concat($passageQuestions);
                    $remaining -= $pCount;
                }
            } else {
                $final->push($q);
                $remaining--;
            }
        }

        return $final;
    }
}
