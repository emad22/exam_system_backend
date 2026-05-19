<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSkill;
use App\Models\StudentAnswer;
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
            $q->whereHas('question', fn($q2) => $q2->whereIn('type', ['writing', 'speaking']))
              ->where('is_manual_graded', false);
        })->with(['student.user', 'exam']);

        if ($request->has('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        $attempts = $query->orderBy('updated_at', 'desc')->paginate(20);

        // Attach pending counts per attempt
        $attempts->getCollection()->transform(function ($attempt) {
            $answers = StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->whereHas('question', fn($q) => $q->whereIn('type', ['writing', 'speaking']))
                ->where('is_manual_graded', false)
                ->with('question:id,type')
                ->get();

            $attempt->pending_writing = $answers->filter(fn($a) => $a->question->type === 'writing')->count();
            $attempt->pending_speaking = $answers->filter(fn($a) => $a->question->type === 'speaking')->count();
            $attempt->total_pending   = $answers->count();

            return $attempt;
        });

        return response()->json($attempts);
    }

    /**
     * Get all writing/speaking answers for a specific attempt, grouped by skill.
     */
    public function showAttempt(ExamAttempt $attempt)
    {
        $answers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereHas('question', fn($q) => $q->whereIn('type', ['writing', 'speaking']))
            ->with(['question.skill'])
            ->orderBy('question_id')
            ->get();

        // Group by skill and attach max_points from exam_skill pivot
        $grouped = $answers->groupBy('skill_id')->map(function ($skillAnswers, $skillId) use ($attempt) {
            $skill = $skillAnswers->first()->question->skill;

            $maxPoints = DB::table('exam_skill')
                ->where('exam_id', $attempt->exam_id)
                ->where('skill_id', $skillId)
                ->value('max_points') ?? 0;

            return [
                'skill_id'   => (int) $skillId,
                'skill_name' => $skill->name ?? 'Unknown',
                'max_points' => (int) $maxPoints,
                'answers'    => $skillAnswers->values(),
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
    public function gradeAttempt(Request $request, ExamAttempt $attempt)
    {
        $request->validate([
            'grades'                    => 'required|array|min:1',
            'grades.*.answer_id'        => 'required|integer|exists:student_answers,id',
            'grades.*.points_awarded'   => 'required|numeric|min:0',
            'grades.*.teacher_feedback' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $affectedSkillIds = [];

            foreach ($request->grades as $grade) {
                $answer = StudentAnswer::where('id', $grade['answer_id'])
                    ->where('exam_attempt_id', $attempt->id)
                    ->firstOrFail();

                $maxAllowed    = $answer->question->points ?? 0;
                $pointsAwarded = min((float) $grade['points_awarded'], $maxAllowed);

                $answer->update([
                    'points_awarded'   => $pointsAwarded,
                    'teacher_feedback' => $grade['teacher_feedback'] ?? null,
                    'is_manual_graded' => true,
                    'is_correct'       => $pointsAwarded > 0,
                ]);

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
            return response()->json(['message' => 'All grades saved successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to save grades: ' . $e->getMessage()], 500);
        }
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
    public function update(Request $request, StudentAnswer $answer)
    {
        $request->validate([
            'points_awarded'   => 'required|numeric|min:0',
            'teacher_feedback' => 'nullable|string',
            'grading_details'  => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $answer->update([
                'points_awarded'   => $request->points_awarded,
                'teacher_feedback' => $request->teacher_feedback,
                'grading_details'  => $request->grading_details,
                'is_manual_graded' => true,
                'is_correct'       => $request->points_awarded > 0,
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
     * Recalculate the skill score in ExamAttemptSkill after grading.
     */
    private function recalculateSkillScore(ExamAttempt $attempt, int $skillId): void
    {
        $totalEarned = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->sum('points_awarded');

        $attemptSkill = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->first();

        if ($attemptSkill) {
            $attemptSkill->update(['score' => $totalEarned]);
        }
    }
}
