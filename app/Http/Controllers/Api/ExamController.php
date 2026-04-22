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

        // Filter exams based on what has been explicitly assigned or is in their package
        $assignedExamIds = $studentProfile->configs()->pluck('exam_id')->toArray();
        
        if ($studentProfile->package && $studentProfile->package->exam_id) {
            if (!in_array($studentProfile->package->exam_id, $assignedExamIds)) {
                $assignedExamIds[] = $studentProfile->package->exam_id;
            }
        }
        
        $exams = Exam::whereIn('id', $assignedExamIds)
            ->with(['language', 'skills'])
            ->get();

        // Priority 1: Specifically assigned in students table
        $allowedSkillIdentifiers = array_filter((array) $studentProfile->assigned_skills);
        
        // Priority 2: Skills defined in the package_id in students table
        if (empty($allowedSkillIdentifiers) && $studentProfile->package && $studentProfile->package->skills) {
            $allowedSkillIdentifiers = array_filter((array) $studentProfile->package->skills);
        }

        // Priority 3: All skills in the exam linked with the package
        if (empty($allowedSkillIdentifiers) && $studentProfile->package && $studentProfile->package->exam) {
            $allowedSkillIdentifiers = $studentProfile->package->exam->skills->pluck('name')->toArray();
        }

        // Attach attempt status and filter visible skills
        $exams->each(function($exam) use ($studentProfile, $allowedSkillIdentifiers) {
            // Restore the skill filtering logic
            if (!empty($allowedSkillIdentifiers)) {
                $filteredSkills = $exam->skills->filter(function($skill) use ($allowedSkillIdentifiers) {
                    $skillName = strtolower(trim($skill->name));
                    $skillCode = strtolower(trim($skill->short_code));
                    
                    foreach ($allowedSkillIdentifiers as $idOrCode) {
                        $match = strtolower(trim($idOrCode));
                        if ($skill->id == $match || $skillName == $match || $skillCode == $match) {
                            return true;
                        }
                    }
                    return false;
                });
                $exam->setRelation('skills', $filteredSkills->values());
            }
            
            // 1. Get the latest attempt for the "Resume" logic
            $exam->latest_attempt = ExamAttempt::where('student_id', $studentProfile->id)
                ->where('exam_id', $exam->id)
                ->with('attemptSkills') 
                ->orderBy('created_at', 'desc')
                ->first();

            // 2. Aggregate all COMPLETED skill IDs across ALL attempts for this student/exam
            $exam->completed_skill_ids = \App\Models\ExamAttemptSkill::whereHas('attempt', function($q) use ($studentProfile, $exam) {
                    $q->where('student_id', $studentProfile->id)->where('exam_id', $exam->id);
                })
                ->whereIn('status', ['completed', 'failed'])
                ->pluck('skill_id')
                ->unique()
                ->values();
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

        // --- NEW: Block Repeated Skill Attempts ---
        if ($request->has('skill_id')) {
            $requestedSkillId = (int)$request->skill_id;
            $hasCompletedSkill = \App\Models\ExamAttemptSkill::whereHas('attempt', function($q) use ($studentProfile, $exam) {
                $q->where('student_id', $studentProfile->id)->where('exam_id', $exam->id);
            })->where('skill_id', $requestedSkillId)
              ->whereIn('status', ['completed', 'failed']) // Statuses that mean "Done"
              ->exists();
            
            if ($hasCompletedSkill) {
                return response()->json(['error' => 'You have already completed the evaluation for this specific module.'], 403);
            }
        }
        // ------------------------------------------

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

            // If no config exists, try to auto-assign it (e.g. from package or defaults)
            if (!$config) {
                $config = \App\Models\Student::assignDefaultExam($studentProfile, $exam->id);
                
                if (!$config) {
                    return response()->json(['error' => 'This exam has not been formally assigned to your account or package.'], 403);
                }
            }

            // 2. Map allowed skills to actual skill IDs available in this exam
            $examSkills = $exam->skills;
            $assignedSkills = [];

            // Get allowed skill identifiers (IDs, names, or codes)
            $allowedSkillIdentifiers = array_filter((array) $studentProfile->assigned_skills);
            if (empty($allowedSkillIdentifiers) && $studentProfile->package) {
                $allowedSkillIdentifiers = array_filter((array) $studentProfile->package->skills);
            }
            if (empty($allowedSkillIdentifiers)) {
                $allowedSkillIdentifiers = $exam->skills->pluck('name')->toArray();
            }

            foreach ($examSkills as $skill) {
                $skillName = strtolower(trim($skill->name));
                $skillCode = strtolower(trim($skill->short_code));
                $shouldInclude = false;

                if (!empty($allowedSkillIdentifiers)) {
                    foreach ($allowedSkillIdentifiers as $idOrCode) {
                        $match = strtolower(trim($idOrCode));
                        if ($skill->id == $match || $skillName == $match || $skillCode == $match) {
                            $shouldInclude = true;
                            break;
                        }
                    }
                } else {
                    // Fallback to config flags if no granular identifiers
                    if ($skillName === 'listening' && $config->want_listening) $shouldInclude = true;
                    if (($skillName === 'reading' || $skillName === 'reading comprehension') && $config->want_reading) $shouldInclude = true;
                    if (($skillName === 'grammar' || $skillName === 'structure') && $config->want_grammar) $shouldInclude = true;
                    if ($skillName === 'writing' && $config->want_writing) $shouldInclude = true;
                    if ($skillName === 'speaking' && $config->want_speaking) $shouldInclude = true;
                }

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
            ->where('level_id', $level->id)
            ->get();

        // 2. If no level-specific rules, fall back to General (Skill-wide) rules
        if ($rules->isEmpty()) {
            $rules = \App\Models\ExamQuestionRule::where('exam_id', $attempt->exam_id)
                ->where('skill_id', $skillId)
                ->whereNull('level_id')
                ->get();
        }

        if ($rules->isNotEmpty()) {
            $questions = collect();
            foreach ($rules as $rule) {
                // Fetch ONLY questions associated with this exam via pivot
                $query = $attempt->exam->questions()
                    ->where('questions.skill_id', $skillId)
                    ->where('questions.level_id', $level->id)
                    ->whereNotIn('questions.id', $answeredIds)
                    ->with(['options', 'passage']);

                $ruleQuestions = $query->inRandomOrder()->take($rule->quantity)->get();
                
                // Expand grouped questions
                $processedQuestions = collect();
                $processedGroups = [];

                foreach ($ruleQuestions as $q) {
                    if ($q->passage_id && !in_array($q->passage_id, $processedGroups)) {
                        $processedGroups[] = $q->passage_id;
                        
                        // Fetch the rest of the questions for this passage that are also in this exam
                        $groupQuestions = $attempt->exam->questions()
                            ->where('questions.passage_id', $q->passage_id)
                            ->with('options')
                            ->orderBy('questions.id', 'asc') // Usually passages have a logical order
                            ->get();

                        $processedQuestions = $processedQuestions->concat($groupQuestions);
                    } elseif (!$q->passage_id) {
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
                $padding = $attempt->exam->questions()
                    ->where('questions.skill_id', $skillId)
                    ->where('questions.level_id', $level->id)
                    ->whereNotIn('questions.id', array_merge($answeredIds, $excludedIds))
                    ->whereNull('questions.passage_id') 
                    ->with('options')
                    ->inRandomOrder()
                    ->take($needed)
                    ->get();
                $questions = $questions->concat($padding);
            }
        } else {
            // Fallback to random level-based selection from the POOL linked to this exam
            $questions = $attempt->exam->questions()
                ->where('questions.skill_id', $skillId)
                ->where('questions.level_id', $level->id)
                ->whereNotIn('questions.id', $answeredIds)
                ->whereNull('questions.passage_id')
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
        $exam = Exam::find($examId);
        $levels = \App\Models\Level::where('skill_id', $skillId)
            ->where('is_active', true)
            ->get();

        $total = 0;
        foreach ($levels as $level) {
            // Check pivot existence
            $hasQuestions = $exam->questions()
                ->where('questions.skill_id', $skillId)
                ->where('questions.level_id', $level->id)
                ->exists();

            if (!$hasQuestions) {
                continue;
            }

            // Check level-specific rules first
            $levelQty = \App\Models\ExamQuestionRule::where('exam_id', $examId)
                ->where('skill_id', $skillId)
                ->where('level_id', $level->id)
                ->sum('quantity');

            if ($levelQty > 0) {
                $total += $levelQty;
            } else {
                // Fall back to general (null) rules for this skill
                $generalQty = \App\Models\ExamQuestionRule::where('exam_id', $examId)
                    ->where('skill_id', $skillId)
                    ->whereNull('level_id')
                    ->sum('quantity');
                $total += $generalQty > 0 ? $generalQty : 5;
            }
        }

        return max($total, 0);
    }

    /**
     * Submit a batch of answers — handles both Adaptive and Not-Adaptive logic.
     *
     * ADAPTIVE:     Always continues to the next level after a pass.
     *               Stops only after passing or failing Level 9.
     *
     * NOT-ADAPTIVE: If student PASSES a level → move to next level (ascending).
     *               If student FAILS a level → STOP this skill immediately.
     *               That failing level is recorded as the student's placement level.
     */
    public function submitBatch(Request $request, ExamAttempt $attempt)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.option_id' => 'nullable|exists:question_options,id',
            'answers.*.text_answer' => 'nullable|string',
            'answers.*.audio_file' => 'nullable|file|mimes:mp3,wav,webm,ogg|max:10240', // Max 10MB
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

        // --- Calculate score for this batch ---
        $correctCount = 0;
        $totalAnswered = count($request->answers);
        
        foreach ($request->answers as $index => $ans) {
            $question = Question::find($ans['question_id']);
            $isCorrect = false;

            if ($question->type === 'mcq' && isset($ans['option_id'])) {
                $option = $question->options()->find($ans['option_id']);
                $isCorrect = $option ? $option->is_correct : false;
            }
            
            // Handle Audio Upload for Speaking tasks
            $mediaPath = null;
            if ($request->hasFile("answers.{$index}.audio_file")) {
                $file = $request->file("answers.{$index}.audio_file");
                $mediaPath = $file->store("attempts/{$attempt->id}/answers", 'public');
            }

            if ($isCorrect) $correctCount++;

            StudentAnswer::updateOrCreate(
                ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'option_id'      => $ans['option_id'] ?? null,
                    'text_answer'    => $ans['text_answer'] ?? null,
                    'media_answer'   => $mediaPath,
                    'is_correct'     => $isCorrect,
                    'points_awarded' => $isCorrect ? $question->points : 0,
                ]
            );
        }

        $batchScore    = $totalAnswered > 0 ? round(($correctCount / $totalAnswered) * 100, 1) : 0;
        $passThreshold = $level->pass_threshold ?? 70;
        $passed        = $batchScore >= $passThreshold;

        // --- Log Level Performance (Movement Log) ---
        \App\Models\ExamAttemptLevel::create([
            'exam_attempt_id' => $attempt->id,
            'skill_id'        => $skillId,
            'level_number'    => $levelNum,
            'score'           => $batchScore,
            'status'          => $passed ? 'passed' : 'failed',
        ]);

        // --- Determine the student's exam mode ---
        $student     = $attempt->student;          // relationship: ExamAttempt->student
        $isAdaptive  = !$student->not_adaptive;    // not_adaptive = 1 means NOT adaptive

        $nextPos     = $pos;
        $finishedExam = false;
        $skillEnded   = false;
        $placementLevel = null;   // For not-adaptive: the level where student stopped
        $placementScore = null;

        // Check if another level exists at all
        $nextLevelExists = \App\Models\Level::where('skill_id', $skillId)
            ->where('level_number', $levelNum + 1)
            ->exists();

        if ($isAdaptive) {
            // ===== ADAPTIVE MODE =====
            // Continue upward through all levels, regardless of passing or failing.
            // Stop only when there are no more levels.
            if ($nextLevelExists) {
                // Move to next level
                $nextPos['current_level'] = $levelNum + 1;
            } else {
                // Done with this skill (reached the last level)
                $skillEnded = true;
                $attempt->attemptSkills()->updateOrCreate(
                    ['skill_id' => $skillId],
                    [
                        'max_level_reached' => $levelNum,
                        'score'             => $batchScore,
                        'status'            => 'completed', // They finished all levels
                        'finished_at'       => now(),
                    ]
                );
            }
        } else {
            // ===== NOT-ADAPTIVE MODE =====
            // FAIL → STOP immediately. This level is the student's placement.
            // PASS → move to next level (if any), otherwise skill is done.
            if (!$passed) {
                // Student FAILED → record placement and stop
                $skillEnded     = true;
                $placementLevel = $levelNum;          // ← "their level"
                $placementScore = $batchScore;

                $attempt->attemptSkills()->updateOrCreate(
                    ['skill_id' => $skillId],
                    [
                        'max_level_reached'  => $levelNum,
                        'score'              => $batchScore,
                        'status'             => 'failed',
                        'placement_level'    => $levelNum,   // store separately for reports
                        'placement_score'    => $batchScore,
                        'finished_at'        => now(),
                    ]
                );
            } elseif ($passed && $nextLevelExists) {
                // Student PASSED and there is a higher level → advance
                $nextPos['current_level'] = $levelNum + 1;
            } else {
                // Student PASSED the final level → skill done (top placement)
                $skillEnded     = true;
                $placementLevel = $levelNum;
                $placementScore = $batchScore;

                $attempt->attemptSkills()->updateOrCreate(
                    ['skill_id' => $skillId],
                    [
                        'max_level_reached'  => $levelNum,
                        'score'              => $batchScore,
                        'status'             => 'completed',
                        'placement_level'    => $levelNum,
                        'placement_score'    => $batchScore,
                        'finished_at'        => now(),
                    ]
                );
            }
        }

        // Move to next skill or finish exam
        if ($skillEnded) {
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
            'passed_level'    => $passed,
            'batch_score'     => $batchScore,
            'skill_ended'     => $skillEnded,
            'finished_exam'   => $finishedExam,
            'placement_level' => $placementLevel,   // null if adaptive or mid-skill
            'placement_score' => $placementScore,
            'is_adaptive'     => $isAdaptive,
            'next_step'       => $finishedExam ? 'results' : ($skillEnded ? 'dashboard' : 'next_batch'),
        ]);
    }
}
