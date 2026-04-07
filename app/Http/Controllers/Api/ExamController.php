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

        // Filter exams based on student's type (adult/children)
        $exams = Exam::where('exam_type', $studentProfile->exam_type)
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

        if (!$attempt) {
            $assignedSkills = $studentProfile->assigned_skills; // Array of skill IDs
            
            if (empty($assignedSkills)) {
                return response()->json(['error' => 'No skills assigned to this student.'], 422);
            }

            $attempt = ExamAttempt::create([
                'student_id' => $studentProfile->id,
                'exam_id' => $exam->id,
                'status' => 'ongoing',
                'current_position' => [
                    'skill_ids' => $assignedSkills,
                    'current_skill_index' => 0,
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
                $ruleQuestions = Question::where('skill_id', $skillId)
                    ->where('difficulty_level', $levelNum)
                    ->when($rule->group_tag, fn($q) => $q->where('group_tag', $rule->group_tag))
                    ->with('options')
                    ->inRandomOrder()
                    ->take($rule->quantity)
                    ->get();
                $questions = $questions->concat($ruleQuestions);
            }
            
            // If rules don't fill a full batch (minimum of 1), pad with random level-appropriate questions
            // We use the first rule's quantity or default to 5 if needed
            $targetBatchSize = $rules->sum('quantity');
            if ($questions->count() < $targetBatchSize) {
                $needed = $targetBatchSize - $questions->count();
                $excludedIds = $questions->pluck('id')->toArray();
                $padding = Question::where('skill_id', $skillId)
                    ->where('difficulty_level', $levelNum)
                    ->whereNotIn('id', $excludedIds)
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
            'questions' => $questions
        ]);
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
