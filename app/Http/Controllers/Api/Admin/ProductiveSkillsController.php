<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptLevel;
use App\Models\ExamAttemptSkill;
use App\Models\Level;
use App\Models\StudentAnswer;
use App\Http\Requests\Admin\ProductiveSkills\GradeAttemptRequest;
use App\Http\Requests\Admin\ProductiveSkills\UpdateAnswerRequest;
use App\Services\AttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\ActivityLog;

class ProductiveSkillsController extends Controller
{
    /**
     * List all exam attempts that have pending writing/speaking answers.
     * Grouped by attempt — one row per student submission.
     */
    public function index(Request $request)
    {
        $query = ExamAttempt::whereHas('answers', function ($q) {
            $q->whereHas('question', fn($q2) => $q2->whereIn('type', ['writing', 'speaking', 'speaking_live', 'pdf_annotation']))
              ->where('is_manual_graded', false);
        })->with(['student.user', 'exam']);

        if ($request->has('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        $attempts = $query->orderBy('updated_at', 'desc')->paginate(20);

        // Attach pending counts per attempt
        $attempts->getCollection()->transform(function ($attempt) {
            $answers = StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->whereHas('question', fn($q) => $q->whereIn('type', ['writing', 'speaking', 'speaking_live', 'pdf_annotation']))
                ->where('is_manual_graded', false)
                ->with('question:id,type')
                ->get();

            $attempt->pending_writing = $answers->filter(fn($a) => $a->question->type === 'writing')->count();
            $attempt->pending_speaking = $answers->filter(fn($a) => in_array($a->question->type, ['speaking', 'speaking_live']))->count();
            $attempt->total_pending   = $answers->count();

            return $attempt;
        });

        return response()->json($attempts);
    }

    /**
     * Get all writing/speaking answers for a specific attempt, grouped by skill+type.
     *
     * Each group is a unique (skill_id, question_type_category) pair so that
     * Writing and Speaking/Speaking-Live answers that share the same skill are
     * never mixed together. This prevents scores from being summed across types.
     */
    public function showAttempt(ExamAttempt $attempt)
    {
        $answers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereHas('question', fn($q) => $q->whereIn('type', ['writing', 'speaking', 'speaking_live', 'pdf_annotation']))
            ->select('id', 'exam_attempt_id', 'question_id', 'skill_id', 'text_answer', 'media_answer', 'word_count', 'points_awarded', 'teacher_feedback', 'grading_details', 'is_manual_graded', 'created_at', 'updated_at')
            ->with(['question.skill'])
            ->orderBy('question_id')
            ->get();

        // Map each question type to a canonical category label
        $typeCategory = fn(string $type): string => match (true) {
            in_array($type, ['speaking', 'speaking_live']) => 'speaking',
            $type === 'writing'                            => 'writing',
            default                                        => $type,
        };

        // Group by "skill_id|category" so writing and speaking are always separate
        $grouped = $answers->groupBy(function ($ans) use ($typeCategory) {
            $category = $typeCategory($ans->question->type ?? 'writing');
            return "{$ans->skill_id}|{$category}";
        })->map(function ($skillAnswers, $groupKey) use ($attempt, $typeCategory) {
            [$skillId, $category] = explode('|', $groupKey, 2);
            $skill = $skillAnswers->first()->question->skill;

            $maxPoints = DB::table('exam_skill')
                ->where('exam_id', $attempt->exam_id)
                ->where('skill_id', (int) $skillId)
                ->value('max_points') ?? 0;

            // For mixed skills, split max_points proportionally by question points
            $totalPossible = $skillAnswers->sum(fn($a) => $a->question->points ?? 0);

            return [
                'skill_id'       => (int) $skillId,
                'question_type'  => $category,           // 'writing' | 'speaking' | …
                'skill_name'     => $skill->name ?? 'Unknown',
                'max_points'     => (int) $maxPoints,
                'total_possible' => (int) $totalPossible,
                'answers'        => $skillAnswers->values(),
            ];
        })->values();

        return response()->json([
            'attempt' => $attempt->load(['student.user', 'exam']),
            'skills'  => $grouped,
        ]);
    }

    /**
     * Bulk-grade all writing/speaking answers for an attempt.
     */
    public function gradeAttempt(GradeAttemptRequest $request, ExamAttempt $attempt)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $affectedSkillIds = [];

            foreach ($validated['grades'] as $grade) {
                $answer = StudentAnswer::where('id', $grade['answer_id'])
                    ->where('exam_attempt_id', $attempt->id)
                    ->firstOrFail();

                $maxAllowed    = $answer->question->points ?? 0;
                $pointsAwarded = min((float) $grade['points_awarded'], $maxAllowed);

                $updateData = [
                    'points_awarded'   => $pointsAwarded,
                    'teacher_feedback' => $grade['teacher_feedback'] ?? null,
                    'is_manual_graded' => true,
                    'is_correct'       => $pointsAwarded > 0,
                ];

                if (array_key_exists('grading_details', $grade)) {
                    $updateData['grading_details'] = $grade['grading_details'];
                }

                $answer->update($updateData);

                $affectedSkillIds[] = $answer->skill_id;
            }

            // Recalculate skill scores for all affected skills
            foreach (array_unique(array_filter($affectedSkillIds)) as $skillId) {
                $this->recalculateSkillScore($attempt, $skillId);
            }

            $studentName = optional(optional(optional($attempt->student)->user))->first_name
                . ' ' . optional(optional(optional($attempt->student)->user))->last_name;

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'updated',
                'model_type'  => ExamAttempt::class,
                'model_id'    => $attempt->id,
                'description' => "Bulk graded Writing/Speaking for: {$studentName}",
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);

            DB::commit();

            // ── Auto-complete the attempt if ALL manual answers are now graded ──
            // Check outside the transaction so we see the committed state
            $this->maybeCompleteAttempt($attempt);

            return response()->json([
                'message'          => 'All grades saved successfully.',
                'attempt_status'   => $attempt->fresh()->status,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to save grades: ' . $e->getMessage()], 500);
        }
    }

    /**
     * If every writing/speaking answer for this attempt has been manually graded,
     * mark the attempt as completed so the report page unlocks the certificate button.
     */
    private function maybeCompleteAttempt(ExamAttempt $attempt): void
    {
        // Only act on non-completed attempts
        if ($attempt->status === 'completed') {
            return;
        }

        $manualTypes = ['writing', 'speaking', 'speaking_live', 'pdf_annotation'];

        // Count answers that still need manual grading
        $pendingCount = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereHas('question', fn($q) => $q->whereIn('type', $manualTypes))
            ->where('is_manual_graded', false)
            ->count();

        if ($pendingCount > 0) {
            // Still ungraded answers — do nothing
            return;
        }

        // All manual answers are graded — finalize the attempt
        app(AttemptService::class)->completeAttempt($attempt);
    }

    /**
     * [Legacy] Get details for a single answer.
     */
    public function show(StudentAnswer $answer)
    {
        return response()->json($answer->load(['attempt.student.user', 'question.passage', 'question.skill']));
    }

    /**
     * [Legacy] Grade a single answer.
     */
    public function update(UpdateAnswerRequest $request, StudentAnswer $answer)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $answer->update([
                'points_awarded'   => $validated['points_awarded'],
                'teacher_feedback' => $validated['teacher_feedback'],
                'grading_details'  => $validated['grading_details'],
                'is_manual_graded' => true,
                'is_correct'       => $validated['points_awarded'] > 0,
            ]);

            $this->recalculateSkillScore($answer->attempt, $answer->skill_id);

            DB::commit();
            return response()->json(['message' => 'Grade saved successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to save grade: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AI grading suggestion for a single answer.
     */
    public function aiSuggest(StudentAnswer $answer)
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'Gemini API key is not configured.'], 503);
        }

        $question      = $answer->question;
        $studentAnswer = $answer->text_answer;
        $maxPoints     = $question->points;
        $minWords      = $question->min_words ?? 0;
        $maxWords      = $question->max_words ?? 0;

        $prompt = <<<PROMPT
            You are an expert English language examiner. Evaluate the following student writing task.
            **Task Instructions / Prompt:** {$question->instructions}
            **Maximum Score:** {$maxPoints} points
            **Word Count Requirement:** {$minWords} - {$maxWords} words
            **Student's Answer:** {$studentAnswer}
            Please evaluate and respond ONLY with a valid JSON object:
            {"suggested_score": <0-{$maxPoints}>, "feedback": "<2-3 sentences>",
             "rubric": {"grammar": <1-5>, "vocabulary": <1-5>},
             "strengths": "<one sentence>", "improvements": "<one sentence>"}
            PROMPT;

        try {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
                ['contents' => [['parts' => [['text' => $prompt]]]],
                 'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 512]]
            );

            if (!$response->successful()) {
                return response()->json(['error' => 'Gemini API error: ' . $response->body()], 502);
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            preg_match('/\{[\s\S]*\}/', $text, $matches);
            if (empty($matches)) {
                return response()->json(['error' => 'Could not parse AI response.'], 500);
            }

            return response()->json(json_decode($matches[0], true));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to contact AI service: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recalculate the skill score in ExamAttemptSkill after manual grading,
     * then sync the status in exam_attempt_skills and exam_attempt_levels,
     * and finally refresh the overall_score on the attempt.
     *
     * Writing / Speaking skills have exactly ONE level (the productive level).
     * We calculate the score as a percentage of total possible points for that
     * level and compare it against the level's pass_threshold to decide
     * passed / failed status — keeping everything consistent with how
     * AttemptService handles auto-graded skills.
     */
    private function recalculateSkillScore(ExamAttempt $attempt, int $skillId): void
    {
        // ── 1. Sum all points awarded for this skill ───────────────────────
        $totalEarned = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->sum('points_awarded');

        // Apply the exam_skill max_points cap if one exists
        $maxPoints = DB::table('exam_skill')
            ->where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->value('max_points');

        if ($maxPoints !== null && $maxPoints > 0) {
            $totalEarned = min($totalEarned, $maxPoints);
        }

        // ── 2. Convert to a percentage score (0-100) ───────────────────────
        // Total possible raw points for this skill in the exam
        $totalPossible = DB::table('questions')
            ->where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->sum('points');

        $scorePercent = $totalPossible > 0
            ? round(($totalEarned / $totalPossible) * 100, 2)
            : 0;

        // ── 3. Determine pass/fail using the level's pass_threshold ────────
        // Productive skills live in a single level — grab it (or default to 70)
        $level = Level::where('skill_id', $skillId)
            ->where('is_active', true)
            ->whereHas('questions', fn($q) => $q->where('exam_id', $attempt->exam_id))
            ->orderBy('level_number')
            ->first();

        $passThreshold = $level->pass_threshold ?? 70;
        $skillStatus   = $scorePercent >= $passThreshold ? 'completed' : 'failed';

        // ── 4. Update exam_attempt_skills ──────────────────────────────────
        $attemptSkill = ExamAttemptSkill::firstOrCreate(
            [
                'exam_attempt_id' => $attempt->id,
                'skill_id'        => $skillId,
            ],
            [
                'started_at' => now(),
                'status'     => 'in_progress',
            ]
        );

        $attemptSkill->update([
            'score'       => $scorePercent,
            'status'      => $skillStatus,
            'finished_at' => now(),
            'started_at'  => $attemptSkill->started_at ?? now(),
        ]);

        // ── 5. Update exam_attempt_levels for this skill's level ───────────
        if ($level) {
            ExamAttemptLevel::updateOrCreate(
                [
                    'exam_attempt_id' => $attempt->id,
                    'skill_id'        => $skillId,
                    'level_number'    => $level->level_number,
                ],
                [
                    'score'  => $scorePercent,
                    'status' => $scorePercent >= $passThreshold ? 'passed' : 'failed',
                ]
            );
        }

        // ── 6. Refresh the attempt's overall_score (Core Skills Only) ───────
        $coreKeywords = ['listen', 'read', 'struct', 'grammar'];
        $coreSkillIds = \App\Models\Skill::where(function ($query) use ($coreKeywords) {
            foreach ($coreKeywords as $word) {
                $query->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($word) . '%']);
            }
        })->pluck('id')->toArray();

        $coreScores = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
            ->whereIn('skill_id', $coreSkillIds)
            ->pluck('score')
            ->map(fn ($s) => (float) $s)
            ->toArray();

        $overall = count($coreScores) > 0
            ? round(array_sum($coreScores) / count($coreScores), 2)
            : 0;

        $attempt->update(['overall_score' => $overall]);
    }
}
