<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentExamConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExamController extends Controller
{
    /**
     * List exams available for the student
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $studentProfile = $user->student;
        
        if (!$studentProfile) {
            return response()->json([]);
        }

        // Filter exams based on what has been explicitly assigned to this student
        $assignedExamIds = $studentProfile->configs()->pluck('exam_id');
        
        $exams = Exam::whereIn('id', $assignedExamIds)
            ->with(['language', 'skills'])
            ->get();

        // Attach attempt status
        $exams->each(function($exam) use ($studentProfile) {
            $exam->latest_attempt = ExamAttempt::where('student_id', $studentProfile->id)
                ->where('exam_id', $exam->id)
                ->orderBy('created_at', 'desc')
                ->first();
        });

        return response()->json($exams);
    }

    /**
     * Start or Resume Exam Attempt
     */
    public function start(Request $request, Exam $exam)
    {
        $user = $request->user();
        $studentProfile = $user->student;

        if (!$studentProfile) {
            return response()->json(['error' => 'Student profile not found.'], 404);
        }
        
        $attempt = ExamAttempt::where('student_id', $studentProfile->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'ongoing')
            ->first();

        if ($attempt) {
            // Case: RESUME but with a specific skill request
            if ($request->has('skill_id')) {
                $requestedSkillId = (int)$request->skill_id;
                $pos = $attempt->current_position;
                $skillIndex = array_search($requestedSkillId, $pos['skill_ids']);
                
                if ($skillIndex !== false) {
                    $pos['current_skill_index'] = $skillIndex;
                    $pos['current_level'] = 1; // Start from Level 1 for the new skill
                    $attempt->update(['current_position' => $pos]);
                }
            }
        } else {
            // 1. Fetch the granular config for this specific student/exam pairing
            $config = StudentExamConfig::where('student_id', $studentProfile->id)
                ->where('exam_id', $exam->id)
                ->first();

            if (!$config) {
                return response()->json(['error' => 'This exam has not been formally assigned to your account.'], 403);
            }

            // 2. Map config flags to actual skill IDs available in this exam
            $examSkills = $exam->skills;
            $assignedSkills = [];

            foreach ($examSkills as $skill) {
                $name = strtolower($skill->name);
                $shouldInclude = false;

                if ($name === 'listening' && $config->want_listening) $shouldInclude = true;
                if (($name === 'reading' || $name === 'reading comprehension') && $config->want_reading) $shouldInclude = true;
                if (($name === 'grammar' || $name === 'structure') && $config->want_grammar) $shouldInclude = true;
                if ($name === 'writing' && $config->want_writing) $shouldInclude = true;
                if ($name === 'speaking' && $config->want_speaking) $shouldInclude = true;

                if ($shouldInclude) {
                    $assignedSkills[] = $skill->id;
                }
            }
            
            if (empty($assignedSkills)) {
                return response()->json(['error' => 'No skills have been activated for your exam attempt. Please contact an administrator.'], 422);
            }

            // Determine starting index
            $startIndex = 0;
            if ($request->has('skill_id')) {
                $foundIndex = array_search((int)$request->skill_id, $assignedSkills);
                if ($foundIndex !== false) {
                    $startIndex = $foundIndex;
                }
            }

            $attempt = ExamAttempt::create([
                'student_id' => $studentProfile->id,
                'exam_id' => $exam->id,
                'status' => 'ongoing',
                'current_position' => [
                    'skill_ids' => $assignedSkills,
                    'current_skill_index' => $startIndex,
                    'current_level' => 1,
                    'completed_skills' => []
                ]
            ]);
        }

        return response()->json([
            'attempt' => $attempt->load('exam'),
            'assigned_skills' => \App\Models\Skill::whereIn('id', $attempt->current_position['skill_ids'])->get()
        ]);
    }

    /**
     * Fetch questions for the current level in the current skill
     */
    public function getNextBatch(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->status !== 'ongoing') {
            return response()->json(['error' => 'Exam is not active.'], 403);
        }

        $pos = $attempt->current_position ?? [];
        if (!isset($pos['skill_ids']) || !isset($pos['skill_ids'][$pos['current_skill_index']])) {
            return response()->json(['error' => 'Exam configuration error: Skill not found.'], 500);
        }
        
        $skillId = $pos['skill_ids'][$pos['current_skill_index']];
        $levelNum = $pos['current_level'];

        // Find the level config
        $level = \App\Models\Level::where('skill_id', $skillId)
            ->where('level_number', $levelNum)
            ->first();

        if (!$level) {
            return response()->json(['error' => "Configuration missing for Level {$levelNum}."], 404);
        }

        // Get questions for this skill and level
        // Exclude already-answered questions to prevent repetition
        $answeredIds = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->pluck('question_id')
            ->toArray();

        // 1. Try to find Level-Specific rules first
        $rules = \App\Models\ExamQuestionRule::where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->where('difficulty_level', $levelNum)
            ->get();

        // 2. If no level-specific rules, fall back to General (Skill-wide) rules
        if ($rules->isEmpty()) {
            $rules = \App\Models\ExamQuestionRule::where('exam_id', $attempt->exam_id)
                ->where('skill_id', $skillId)
                ->whereNull('difficulty_level')
                ->get();
        }

        if ($rules->isNotEmpty()) {
            $questions = collect();
            foreach ($rules as $rule) {
                $query = Question::where('skill_id', $skillId)
                    ->where('difficulty_level', $levelNum)
                    ->whereNotIn('id', $answeredIds)
                    ->when($rule->group_tag, fn($q) => $q->where('group_tag', $rule->group_tag))
                    ->with('options');

                $ruleQuestions = $query->inRandomOrder()->take($rule->quantity)->get();
                
                // Expand grouped questions
                $processedQuestions = collect();
                $processedGroups = [];

                foreach ($ruleQuestions as $q) {
                    if ($q->passage_group_id && !in_array($q->passage_group_id, $processedGroups)) {
                        $processedGroups[] = $q->passage_group_id;
                        $groupQuestions = Question::where('passage_group_id', $q->passage_group_id)
                            ->with('options')
                            ->get();
                        
                        // Internal randomization
                        if ($groupQuestions->first() && $groupQuestions->first()->passage_randomize) {
                            $groupQuestions = $groupQuestions->shuffle();
                        }

                        $processedQuestions = $processedQuestions->concat($groupQuestions);
                    } elseif (!$q->passage_group_id) {
                        $processedQuestions->push($q);
                    }
                }
                
                $questions = $questions->concat($processedQuestions);
            }
            
            // If rules don't fill a full batch (minimum of 1), pad with random level-appropriate questions
            $targetBatchSize = $rules->sum('quantity');
            if ($questions->count() < $targetBatchSize) {
                $needed = $targetBatchSize - $questions->count();
                $excludedIds = $questions->pluck('id')->toArray();
                $padding = Question::where('skill_id', $skillId)
                    ->where('difficulty_level', $levelNum)
                    ->whereNotIn('id', $excludedIds)
                    ->whereNull('passage_group_id') // Padding shouldn't accidentally pull massive passages
                    ->with('options')
                    ->inRandomOrder()
                    ->take($needed)
                    ->get();
                $questions = $questions->concat($padding);
            }
        } else {
            // Fallback to random level-based selection (Default Batch Size: 5)
            $questions = Question::where('skill_id', $skillId)
                ->where('difficulty_level', $levelNum)
                ->whereNotIn('id', $answeredIds)
                ->whereNull('passage_group_id')
                ->with('options')
                ->inRandomOrder()
                ->take(5) 
                ->get();
        }

        if ($questions->isEmpty()) {
            return response()->json([
                'error' => "No questions found for {$level->name} in " . \App\Models\Skill::find($skillId)->name,
                'is_empty' => true
            ], 404);
        }

        return response()->json([
            'skill' => \App\Models\Skill::find($skillId),
            'level' => $level,
            'questions' => $questions,
            'total_questions' => $this->getTotalSkillQuestions($attempt->exam_id, $skillId),
        ]);
    }

    /**
     * Calculate total expected questions for a skill in an exam.
     * Used for the global question counter on the frontend.
     */
    private function getTotalSkillQuestions(int $examId, int $skillId): int
    {
        $levels = \App\Models\Level::where('skill_id', $skillId)
            ->where('is_active', true)
            ->get();

        $total = 0;
        foreach ($levels as $level) {
            // Check level-specific rules first
            $levelQty = \App\Models\ExamQuestionRule::where('exam_id', $examId)
                ->where('skill_id', $skillId)
                ->where('difficulty_level', $level->level_number)
                ->sum('quantity');

            if ($levelQty > 0) {
                $total += $levelQty;
            } else {
                // Fall back to general (null) rules for this skill
                $generalQty = \App\Models\ExamQuestionRule::where('exam_id', $examId)
                    ->where('skill_id', $skillId)
                    ->whereNull('difficulty_level')
                    ->sum('quantity');
                $total += $generalQty > 0 ? $generalQty : 5;
            }
        }

        return max($total, 0);
    }

    /**
     * Submit a batch of answers and decide the next step (Adaptive Logic)
     */
    public function submitBatch(Request $request, ExamAttempt $attempt)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.option_id' => 'nullable|exists:question_options,id',
            'answers.*.text_answer' => 'nullable|string',
        ]);

        $pos = $attempt->current_position ?? [];
        if (!isset($pos['skill_ids'])) {
            return response()->json(['error' => 'Current position is invalid.'], 500);
        }
        
        $skillId = $pos['skill_ids'][$pos['current_skill_index']];
        $levelNum = $pos['current_level'];

        $level = \App\Models\Level::where('skill_id', $skillId)
            ->where('level_number', $levelNum)
            ->first();

        // Calculate score for this batch
        $correctCount = 0;
        foreach ($request->answers as $ans) {
            $question = Question::find($ans['question_id']);
            $isCorrect = false;
            
            if ($question->type === 'mcq' && isset($ans['option_id'])) {
                $option = $question->options()->find($ans['option_id']);
                $isCorrect = $option ? $option->is_correct : false;
            }
            // Add logic for other types...

            if ($isCorrect) $correctCount++;

            StudentAnswer::updateOrCreate(
                ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'option_id' => $ans['option_id'] ?? null,
                    'text_answer' => $ans['text_answer'] ?? null,
                    'is_correct' => $isCorrect,
                    'points_awarded' => $isCorrect ? $question->points : 0
                ]
            );
        }

        $batchScore = ($correctCount / count($request->answers)) * 100;
        $passed = $batchScore >= ($level->pass_threshold ?? 70);

        $nextPos = $pos;
        $finishedExam = false;
        $skillEnded = false;

        if ($passed && $levelNum < 9) {
            // Move up
            $nextPos['current_level'] = $levelNum + 1;
        } else {
            // STOP criteria met (Failed or Level 9 reached)
            $skillEnded = true;
            
            // Record result for this skill
            $attempt->attemptSkills()->updateOrCreate(
                ['skill_id' => $skillId],
                [
                    'max_level_reached' => $levelNum,
                    'status' => $passed ? 'completed' : 'failed',
                    'finished_at' => now()
                ]
            );

            // Move to NEXT SKILL
            if ($pos['current_skill_index'] < count($pos['skill_ids']) - 1) {
                $nextPos['current_skill_index']++;
                $nextPos['current_level'] = 1;
            } else {
                $finishedExam = true;
            }
        }

        $attempt->update(['current_position' => $nextPos]);

        if ($finishedExam) {
            $attempt->update(['status' => 'completed', 'finished_at' => now()]);
        }

        return response()->json([
            'passed_level' => $passed,
            'batch_score' => $batchScore,
            'skill_ended' => $skillEnded,
            'finished_exam' => $finishedExam,
            'next_step' => $finishedExam ? 'results' : 'next_batch'
        ]);
    }
}
