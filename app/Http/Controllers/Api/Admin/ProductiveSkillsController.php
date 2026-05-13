<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentAnswer;
use App\Models\ExamAttemptSkill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ProductiveSkillsController extends Controller
{
    /**
     * List all student answers that need manual grading (Writing, Speaking, etc.)
     */
    public function index(Request $request)
    {
        $query = StudentAnswer::with(['attempt.student.user', 'question.skill'])
            ->whereHas('question', function ($q) {
                $q->whereIn('type', ['writing', 'speaking']);
            })
            ->where('is_manual_graded', false);

        if ($request->has('exam_id')) {
            $query->whereHas('attempt', function ($q) use ($request) {
                $q->where('exam_id', $request->exam_id);
            });
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate(20));
    }

    /**
     * Get details for a specific answer to be graded
     */
    public function show(StudentAnswer $answer)
    {
        return response()->json($answer->load(['attempt.student.user', 'question.passage', 'question.skill']));
    }

    /**
     * Get AI-generated grading suggestions using Google Gemini
     */
    public function aiSuggest(StudentAnswer $answer)
    {
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Gemini API key is not configured. Add GEMINI_API_KEY to your .env file.',
                'setup_url' => 'https://aistudio.google.com/app/apikey'
            ], 503);
        }

        $question = $answer->question;
        $studentAnswer = $answer->text_answer;
        $maxPoints = $question->points;
        $minWords = $question->min_words ?? 0;
        $maxWords = $question->max_words ?? 0;

        $prompt = <<<PROMPT
You are an expert English language examiner. Evaluate the following student writing task.

**Task Instructions / Prompt:**
{$question->instructions}

**Maximum Score:** {$maxPoints} points
**Word Count Requirement:** {$minWords} - {$maxWords} words

**Student's Answer:**
{$studentAnswer}

Please evaluate this answer and respond ONLY with a valid JSON object in this exact format:
{
  "suggested_score": <number between 0 and {$maxPoints}>,
  "feedback": "<2-3 sentences of constructive feedback in English>",
  "rubric": {
    "grammar": <score 1-5>,
    "vocabulary": <score 1-5>,
    "coherence": <score 1-5>,
    "task_achievement": <score 1-5>
  },
  "strengths": "<one sentence about what the student did well>",
  "improvements": "<one sentence about the main area to improve>"
}
PROMPT;

        try {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 512,
                    ]
                ]
            );

            if (!$response->successful()) {
                return response()->json(['error' => 'Gemini API error: ' . $response->body()], 502);
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            // Extract JSON from the response text
            preg_match('/\{[\s\S]*\}/', $text, $matches);
            if (empty($matches)) {
                return response()->json(['error' => 'Could not parse AI response.'], 500);
            }

            $suggestion = json_decode($matches[0], true);

            return response()->json($suggestion);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to contact AI service: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Submit grade and feedback for an answer
     */
    public function update(Request $request, StudentAnswer $answer)
    {
        $request->validate([
            'points_awarded' => 'required|numeric|min:0',
            'teacher_feedback' => 'nullable|string',
            'grading_details' => 'nullable|array'
        ]);

        try {
            DB::beginTransaction();

            $answer->update([
                'points_awarded' => $request->points_awarded,
                'teacher_feedback' => $request->teacher_feedback,
                'grading_details' => $request->grading_details,
                'is_manual_graded' => true,
                'is_correct' => $request->points_awarded > 0 
            ]);

            // Recalculate skill score for the attempt
            $this->recalculateSkillScore($answer);

            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'updated',
                'model_type' => StudentAnswer::class,
                'model_id' => $answer->id,
                'description' => "Graded Writing Task for: " . ($answer->attempt->student->user->name ?? 'Unknown'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();
            return response()->json(['message' => 'Grade saved successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to save grade: ' . $e->getMessage()], 500);
        }
    }

    private function recalculateSkillScore(StudentAnswer $answer)
    {
        $attempt = $answer->attempt;
        $skillId = $answer->skill_id;

        // Sum points awarded for this skill in this attempt
        $totalPointsEarned = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->sum('points_awarded');

        // Note: For Writing tasks, we usually calculate a percentage based on max points.
        // In this system, AttemptService::computeSkillScore handles percentage.
        // We'll update the record so computeSkillScore picks it up, or update it directly here.
        
        $attemptSkill = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->first();

        if ($attemptSkill) {
            // If the system uses percentages, we should calculate it. 
            // For now, let's just update the score field.
            $attemptSkill->update([
                'score' => $totalPointsEarned 
            ]);
        }
    }
}
