<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptLevel;
use App\Models\ExamAttemptSkill;
use App\Models\ExamQuestionRule;
use App\Models\Level;
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

        // Admin role
        if ($user->role === 'admin') {
            return response()->json(Exam::with('category', 'skills')->get());
        }

        // Demo role
        if (in_array($user->role, ['demo', 'deom'])) {
            $exams = Exam::with(['category', 'skills'])->get();
            $exams->each(function ($exam) use ($user) {
                $exam->latest_attempt = ExamAttempt::where('user_id', $user->id)
                    ->where('exam_id', $exam->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $exam->completed_skill_ids = [];
            });
            return response()->json($exams);
        }

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
        $exams->each(function ($exam) use ($studentProfile, $allowedSkillIdentifiers) {
            // Restore the skill filtering logic
            if (!empty($allowedSkillIdentifiers)) {
                $filteredSkills = $exam->skills->filter(function ($skill) use ($allowedSkillIdentifiers) {
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

            // 2. Aggregate COMPLETED skill IDs ONLY for the latest attempt (if it exists)
            if ($exam->latest_attempt) {
                $exam->completed_skill_ids = \App\Models\ExamAttemptSkill::where('exam_attempt_id', $exam->latest_attempt->id)
                    ->whereIn('status', ['completed', 'failed'])
                    ->pluck('skill_id')
                    ->unique()
                    ->values();
            } else {
                $exam->completed_skill_ids = [];
            }
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

        if (!$studentProfile && !in_array($user->role, ['demo', 'deom'])) {
            return response()->json(['error' => 'Student profile not found.'], 404);
        }

        // --- NEW: Block Repeated Skill Attempts ---
        if ($request->has('skill_id') && !in_array($user->role, ['demo', 'deom'])) {
            $requestedSkillId = (int) $request->skill_id;
            $hasCompletedSkill = \App\Models\ExamAttemptSkill::whereHas('attempt', function ($q) use ($studentProfile, $exam) {
                $q->where('student_id', $studentProfile->id)->where('exam_id', $exam->id);
            })->where('skill_id', $requestedSkillId)
                ->whereIn('status', ['completed', 'failed']) // Statuses that mean "Done"
                ->exists();

            if ($hasCompletedSkill) {
                return response()->json(['error' => 'You have already completed the evaluation for this specific module.'], 403);
            }
        }
        // ------------------------------------------

        $attempt = ExamAttempt::where(in_array($user->role, ['demo', 'deom']) ? 'user_id' : 'student_id', in_array($user->role, ['demo', 'deom']) ? $user->id : $studentProfile->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'ongoing')
            ->first();

        if ($attempt) {
            // Case: RESUME but with a specific skill request
            if ($request->has('skill_id')) {
                $requestedSkillId = (int) $request->skill_id;
                $pos = $attempt->current_position;
                $skillIndex = array_search($requestedSkillId, $pos['skill_ids']);

                if ($skillIndex !== false) {
                    // --- NEW: For Demo users, if they re-enter a FINISHED skill, close the current attempt and start a fresh one ---
                    // This preserves the "Report" of the previous run while allowing a fresh start.
                    if (in_array($user->role, ['demo', 'deom', 'staff'])) {
                        $isFinished = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
                            ->where('skill_id', $requestedSkillId)
                            ->whereIn('status', ['completed', 'failed'])
                            ->exists();

                        if ($isFinished) {
                            $attempt->update(['status' => 'completed', 'finished_at' => now()]);
                            $attempt = ExamAttempt::create([
                                'user_id' => $user->id,
                                'exam_id' => $exam->id,
                                'status' => 'ongoing',
                                'current_position' => [
                                    'skill_ids' => $pos['skill_ids'],
                                    'current_skill_index' => $skillIndex,
                                    'current_level' => $this->getValidStartingLevel($exam->id, $requestedSkillId, $request->has('level_id') ? (int) $request->level_id : 1),
                                    'current_skill_started_at' => null
                                ]
                            ]);
                            $pos = $attempt->current_position; 
                        } else {
                            StudentAnswer::where('exam_attempt_id', $attempt->id)
                                ->whereHas('question', function ($q) use ($requestedSkillId) {
                                    $q->where('skill_id', $requestedSkillId);
                                })->delete();

                            ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                                ->where('skill_id', $requestedSkillId)
                                ->delete();
                            
                            $pos['current_level'] = $this->getValidStartingLevel($exam->id, $requestedSkillId, $request->has('level_id') ? (int) $request->level_id : 1);
                        }
                    }

                    // --- FIX: If switching to a NEW skill, reset the timer start time to null ---
                    if ($pos['current_skill_index'] !== $skillIndex) {
                        $pos['current_skill_started_at'] = null;
                        if (!in_array($user->role, ['demo', 'deom'])) {
                            $pos['current_level'] = 1;
                        }
                    }
                    
                    $pos['current_skill_index'] = $skillIndex;
                    $attempt->update(['current_position' => $pos]);
                }
            }
        } else {
            // 1. Fetch the granular config for this specific student/exam pairing
            $config = StudentExamConfig::where('student_id', $studentProfile->id ?? 0)
                ->where('exam_id', $exam->id)
                ->first();

            // If no config exists, try to auto-assign it (e.g. from package or defaults)
            if (!$config && !in_array($user->role, ['demo', 'deom'])) {
                $config = \App\Models\Student::assignDefaultExam($studentProfile, $exam->id);

                if (!$config) {
                    return response()->json(['error' => 'This exam has not been formally assigned to your account or package.'], 403);
                }
            }

            // 2. Map allowed skills to actual skill IDs available in this exam
            $examSkills = $exam->skills;
            $assignedSkills = [];

            // Get allowed skill identifiers (IDs, names, or codes)
            $allowedSkillIdentifiers = [];
            if ($studentProfile) {
                $allowedSkillIdentifiers = array_filter((array) $studentProfile->assigned_skills);
                if (empty($allowedSkillIdentifiers) && $studentProfile->package) {
                    $allowedSkillIdentifiers = array_filter((array) $studentProfile->package->skills);
                }
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
                } else if ($config) {
                    // Fallback to config flags if no granular identifiers
                    if ($skillName === 'listening' && $config->want_listening)
                        $shouldInclude = true;
                    if (($skillName === 'reading' || $skillName === 'reading comprehension') && $config->want_reading)
                        $shouldInclude = true;
                    if (($skillName === 'grammar' || $skillName === 'structure') && $config->want_grammar)
                        $shouldInclude = true;
                    if ($skillName === 'writing' && $config->want_writing)
                        $shouldInclude = true;
                    if ($skillName === 'speaking' && $config->want_speaking)
                        $shouldInclude = true;
                } else {
                    $shouldInclude = true;
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
                $foundIndex = array_search((int) $request->skill_id, $assignedSkills);
                if ($foundIndex !== false) {
                    $startIndex = $foundIndex;
                }
            }

            $attempt = ExamAttempt::create([
                'student_id' => $studentProfile ? $studentProfile->id : null,
                'user_id' => in_array($user->role, ['demo', 'deom']) ? $user->id : null,
                'exam_id' => $exam->id,
                'status' => 'ongoing',
                'current_position' => [
                    'skill_ids' => $assignedSkills,
                    'current_skill_index' => $startIndex,
                    'current_level' => $this->getValidStartingLevel($exam->id, $assignedSkills[$startIndex] ?? 0, (in_array($user->role, ['demo', 'deom', 'staff']) && $request->has('level_id')) ? (int) $request->level_id : 1),
                    'completed_skills' => [],
                    'current_skill_started_at' => null // Timer starts only when getNextBatch is called
                ]
            ]);
        }

        return response()->json([
            'attempt' => $attempt->load('exam'),
            'assigned_skills' => \App\Models\Skill::whereIn('id', $attempt->current_position['skill_ids'])->get()
        ]);
    }

    /**
     * Fetch questions for the current level in the current skill.
     * Supports composition rules (standalone_quantity + passage_quantity) and is_random per level.
     */
    public function getNextBatch(Request $request, ExamAttempt $attempt, $retryCount = 0)
    {
        if ($retryCount > 10) {
            return response()->json(['error' => 'Infinite recursion detected. Please ensure your exam has questions assigned.'], 500);
        }
        $user = $request->user();
        \Illuminate\Support\Facades\Log::info("getNextBatch hit for Attempt ID: " . $attempt->id);

        if ($attempt->status !== 'ongoing') {
            return response()->json(['error' => 'Exam is not active.'], 403);
        }

        $pos = $attempt->current_position ?? [];
        if (!isset($pos['skill_ids']) || !isset($pos['skill_ids'][$pos['current_skill_index']])) {
            return response()->json(['error' => 'Exam configuration error: Skill not found.'], 500);
        }

        $skillId = $pos['skill_ids'][$pos['current_skill_index']];
        $levelNum = $pos['current_level'];

        $level = Level::where('skill_id', $skillId)
            ->where('level_number', $levelNum)
            ->first();

        if (!$level) {
            return response()->json(['error' => "Configuration missing for Level {$levelNum}."], 404);
        }

        // Exclude already-answered questions using an efficient Left Join in the main queries
        // instead of pulling a large array into memory.
        $attemptId = $attempt->id;



        // Find level-specific rules first, then fall back to skill-wide rules
        $rules = ExamQuestionRule::where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->get();

        if ($rules->isEmpty()) {
            $rules = ExamQuestionRule::where('exam_id', $attempt->exam_id)
                ->where('skill_id', $skillId)
                ->whereNull('level_id')
                ->get();
        }



        // 1. Normalize Rules: If no specific rules exist, create a virtual rule from Level defaults
        if ($rules->isEmpty()) {
            $rules = collect([(object)[
                'standalone_quantity' => $level->default_standalone_quantity ?? 0,
                'passage_quantity' => $level->default_passage_quantity ?? 0,
                'quantity' => $level->default_question_count ?? 0,
            ]]);
        }

        $questions = collect();

        foreach ($rules as $rule) {
            $sQty = $rule->standalone_quantity ?? 0;
            $pQty = $rule->passage_quantity ?? 0;
            $lQty = $rule->quantity ?? 0;

            // Normalize: If all are 0, use defaults
            if ($sQty === 0 && $pQty === 0 && $lQty === 0) {
                $sQty = $level->default_standalone_quantity ?? 0;
                $pQty = $level->default_passage_quantity ?? 0;
                $lQty = $level->default_question_count ?? 0;
            }

            if ($sQty > 0 || $pQty > 0) {
                if ($sQty > 0) {
                    $questions = $questions->concat($this->fetchStandaloneBatch($attempt->exam_id, $attemptId, $skillId, $level, $sQty));
                }
                if ($pQty > 0) {
                    $questions = $questions->concat($this->fetchPassageBatch($attempt->exam_id, $attemptId, $skillId, $level, $pQty));
                }
            } else if ($lQty > 0) {
                $questions = $questions->concat($this->fetchLegacyBatch($attempt->exam_id, $attemptId, $skillId, $level, $lQty));
            }
        }

        // Final Deduplication & Cleanup
        $questions = $questions->unique('id')->values();

        // Shuffle options
        $questions = $questions->map(function ($q) {
            $q->setRelation('options', $q->options->shuffle());
            return $q;
        });

        if ($questions->isEmpty()) {
            // --- NEW: Fail-Safe for Demo Users ---
            if (in_array($user->role, ['demo', 'deom', 'staff']) && $levelNum > 1) {
                \Illuminate\Support\Facades\Log::warning("Demo user reached end of questions at Level {$levelNum}. Auto-resetting to Level 1.");
                
                StudentAnswer::where('exam_attempt_id', $attempt->id)
                    ->whereHas('question', function ($q) use ($skillId) {
                        $q->where('skill_id', $skillId);
                    })->delete();

                ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->where('skill_id', $skillId)->delete();
                $pos['current_level'] = 1;
                $attempt->update(['current_position' => $pos]);
                return $this->getNextBatch($request, $attempt, $retryCount + 1);
            }

            // --- NEW: Fail-Safe for Demo Users (Level 1 Empty) ---
            if (in_array($user->role, ['demo', 'deom', 'staff']) && $levelNum == 1) {
                $skills = $attempt->exam->skills()->orderBy('id', 'asc')->get();
                $nextSkillIndex = $pos['current_skill_index'] + 1;

                if ($nextSkillIndex < $skills->count()) {
                    \Illuminate\Support\Facades\Log::info("Demo Level 1 empty for Skill Index {$pos['current_skill_index']}. Auto-advancing to next skill.");
                    $pos['current_skill_index'] = $nextSkillIndex;
                    $pos['current_level'] = 1;
                    $attempt->update(['current_position' => $pos]);
                    
                    return $this->getNextBatch($request, $attempt, $retryCount + 1);
                }
            }

            return response()->json([
                'error' => "Empty Question Set: No questions found for level '{$level->name}' (Skill ID: {$skillId}). Please verify that questions are assigned to this level and linked to the exam.",
                'is_empty' => true,
                'debug' => [
                    'skill_id' => $skillId,
                    'level_id' => $level->id,
                    'attempt_id' => $attempt->id,
                    'exam_id' => $attempt->exam_id
                ]
            ], 404);
        }

        // --- FIX: Initialize timer ONLY when the first batch is fetched (Launch clicked) ---
        if (!isset($pos['current_skill_started_at']) || $pos['current_skill_started_at'] === null) {
            $pos['current_skill_started_at'] = now()->toIso8601String();
            $attempt->update(['current_position' => $pos]);
        }

        $skillDuration = \Illuminate\Support\Facades\DB::table('exam_skill')
            ->where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->value('duration') ?? 0;

        // --- NEW: Force 0 duration for demo/staff to disable frontend timer ---
        $isDemo = in_array($user->role, ['demo', 'deom', 'staff']);
        $finalDuration = $isDemo ? 0 : $skillDuration;

        return response()->json([
            'skill' => \App\Models\Skill::find($skillId),
            'level' => $level,
            'questions' => $questions,
            'total_questions' => $this->getTotalSkillQuestions($attempt->exam_id, $skillId),
            'timer_type' => $attempt->exam->timer_type ?? 'global',
            'time_limit' => $attempt->exam->time_limit ?? 0,
            'skill_duration' => $finalDuration,
            'current_skill_started_at' => $pos['current_skill_started_at'],
        ]);
    }


    /**
     * Calculate total expected questions for a skill in an exam.
     * Used for the global question counter on the frontend.
     */
    private function getTotalSkillQuestions(int $examId, int $skillId): int
    {
        $exam = Exam::find($examId); // Load once outside the loop to avoid N+1

        // Pre-load all rules for this exam+skill to avoid N+1 inside the loop
        $allRules = ExamQuestionRule::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->get();
        $rulesByLevel = $allRules->whereNotNull('level_id')->groupBy('level_id');
        $globalRules  = $allRules->whereNull('level_id');

        $levels = Level::where('skill_id', $skillId)
            ->where('is_active', true)
            ->get();

        $total = 0;
        foreach ($levels as $level) {
            // Use pre-loaded rules — no extra queries
            $rules = $rulesByLevel->get($level->id, collect());
            if ($rules->isEmpty()) {
                $rules = $globalRules;
            }

            if ($rules->isNotEmpty()) {
                foreach ($rules as $rule) {
                    $total += ($rule->standalone_quantity ?? 0);

                    if ($rule->passage_quantity > 0) {
                        $passages = Question::where('skill_id', $skillId)
                            ->where('level_id', $level->id)
                            ->whereNotNull('passage_id')
                            ->select('passage_id')
                            ->distinct()
                            ->take($rule->passage_quantity)
                            ->pluck('passage_id');

                        $total += Question::whereIn('passage_id', $passages)->count();
                    }

                    // Legacy quantity fallback
                    if (($rule->quantity ?? 0) > 0 && ($rule->standalone_quantity ?? 0) == 0 && ($rule->passage_quantity ?? 0) == 0) {
                        $total += $rule->quantity;
                    }
                }
            } else {
                // Fallback to level defaults
                $standalone = $level->default_standalone_quantity ?? 0;
                $passageQty = $level->default_passage_quantity ?? 0;
                $legacy = $level->default_question_count ?? 0;

                if ($standalone > 0 || $passageQty > 0) {
                    $total += $standalone;
                    if ($passageQty > 0) {
                        $passages = Question::where('skill_id', $skillId)
                            ->where('level_id', $level->id)
                            ->whereNotNull('passage_id')
                            ->select('passage_id')
                            ->distinct()
                            ->take($passageQty)
                            ->pluck('passage_id');
                        $total += Question::whereIn('passage_id', $passages)->count();
                    }
                } else {
                    if ($legacy > 0) {
                        $total += $legacy;
                    } else {
                        // Unlimited mode (legacy = 0): use pre-loaded $exam — no extra query
                        $total += $exam->questions()
                            ->where('questions.skill_id', $skillId)
                            ->where('questions.level_id', $level->id)
                            ->count();
                    }
                }
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
        \Illuminate\Support\Facades\Log::info('Submit Batch Request:', $request->all());
        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.option_id' => 'nullable|exists:question_options,id',
            'answers.*.text_answer' => 'nullable|string',
            'answers.*.audio_file' => 'nullable|file|max:20480', // Max 20MB
        ]);

        $pos = $attempt->current_position ?? [];
        if (!isset($pos['skill_ids'])) {
            return response()->json(['error' => 'Current position is invalid.'], 500);
        }

        $skillId = $pos['skill_ids'][$pos['current_skill_index']];
        $levelNum = $pos['current_level'];

        $level = Level::where('skill_id', $skillId)
            ->where('level_number', $levelNum)
            ->first();

        // --- Calculate score for this batch (Weighted by points) ---
        $earnedPoints = 0;
        $totalPossiblePoints = 0;
        $resultsMap = []; // To track which questions were wrong

        // Pre-fetch all questions and options to avoid N+1 query problem
        $questionIds = collect($request->answers)->pluck('question_id')->unique()->toArray();
        $questionsMap = Question::with('options')->whereIn('id', $questionIds)->get()->keyBy('id');

        foreach ($request->answers as $index => $ans) {
            $question = $questionsMap->get($ans['question_id']);
            if (!$question) continue;

            $totalPossiblePoints += $question->points;
            $isCorrect = false;

            // Handle MCQs
            if ($question->type === 'mcq' && isset($ans['option_id'])) {
                $option = $question->options->firstWhere('id', (int) $ans['option_id']);
                $isCorrect = $option ? (bool)$option->is_correct : false;
            } 
            // Handle Drag & Drop (Gap Fill)
            else if ($question->type === 'drag_drop' && isset($ans['text_answer'])) {
                $studentAnswers = json_decode($ans['text_answer'], true);
                if (is_array($studentAnswers)) {
                    // Correct options in order of their ID (or sort_order if we had one)
                    $correctOptions = $question->options()->where('is_correct', true)->orderBy('id', 'asc')->pluck('option_text')->toArray();
                    
                    if (count($studentAnswers) === count($correctOptions)) {
                        $isCorrect = true;
                        foreach ($studentAnswers as $i => $val) {
                            if (trim(strtolower($val)) !== trim(strtolower($correctOptions[$i] ?? ''))) {
                                $isCorrect = false;
                                break;
                            }
                        }
                    }
                }
            }
            // Handle Word Selection / Click Word
            else if (in_array($question->type, ['word_selection', 'click_word'])) {
                $studentSelected = $ans['selected_words'] ?? [];
                
                // If it came as a JSON string (legacy or alternate format), decode it
                if (is_string($studentSelected)) {
                    $studentSelected = json_decode($studentSelected, true) ?? [];
                }

                if (is_array($studentSelected)) {
                    $correctOptions = $question->options()->where('is_correct', true)->pluck('option_text')->toArray();
                    $incorrectOptions = $question->options()->where('is_correct', false)->pluck('option_text')->toArray();
                    
                    // All correct words must be selected
                    $allCorrectSelected = true;
                    foreach ($correctOptions as $correct) {
                        if (!in_array($correct, $studentSelected)) {
                            $allCorrectSelected = false;
                            break;
                        }
                    }
                    
                    // No incorrect words should be selected
                    $anyIncorrectSelected = false;
                    foreach ($studentSelected as $selected) {
                        if (in_array($selected, $incorrectOptions)) {
                            $anyIncorrectSelected = true;
                            break;
                        }
                    }
                    
                    $isCorrect = $allCorrectSelected && !$anyIncorrectSelected && count($studentSelected) === count($correctOptions);
                }
            }
            // Handle Fill in the Blank
            else if ($question->type === 'fill_blank') {
                $studentAnswers = $ans['fill_blank_answers'] ?? [];
                $correctOptions = $question->options()->orderBy('id', 'asc')->pluck('option_text')->toArray();
                
                $isCorrect = true;
                if (count($studentAnswers) < count($correctOptions)) {
                    $isCorrect = false;
                } else {
                    foreach ($correctOptions as $i => $correctVal) {
                        if (trim(strtolower($studentAnswers[$i] ?? '')) !== trim(strtolower($correctVal))) {
                            $isCorrect = false;
                            break;
                        }
                    }
                }
            }
            // Handle Drag & Drop
            else if ($question->type === 'drag_drop') {
                $studentAnswers = $ans['drag_drop_answers'] ?? [];
                $correctOptions = $question->options()->orderBy('id', 'asc')->pluck('option_text')->toArray();
                
                $isCorrect = true;
                if (count($studentAnswers) < count($correctOptions)) {
                    $isCorrect = false;
                } else {
                    foreach ($correctOptions as $i => $correctVal) {
                        if (trim($studentAnswers[$i] ?? '') !== trim($correctVal)) {
                            $isCorrect = false;
                            break;
                        }
                    }
                }
            }
            // Handle Matching
            else if ($question->type === 'matching') {
                $studentMatches = $ans['matching_answers'] ?? [];
                if (is_string($studentMatches)) {
                    $studentMatches = json_decode($studentMatches, true) ?? [];
                }
                
            else if ($question->type === 'matching') {
                $studentMatches = $ans['matching_answers'] ?? [];
                if (is_string($studentMatches)) {
                    $studentMatches = json_decode($studentMatches, true) ?? [];
                }
                
                $options = $question->options;
                $isCorrect = true;
                $pairCount = 0;

                foreach ($options as $opt) {
                    $text = $opt->option_text;
                    if (str_contains($text, '|')) {
                        $pairCount++;
                        $parts = explode('|', $text, 2);
                        $expectedTarget = trim($parts[1] ?? '');
                        
                        // Student sends: { option_id: "Target Text" }
                        $actualTarget = $studentMatches[$opt->id] ?? null;
                        
                        if (trim($actualTarget ?? '') !== $expectedTarget) {
                            $isCorrect = false;
                            break;
                        }
                    }
                }
                
                // Ensure student matched all required pairs
                if ($isCorrect && count($studentMatches) !== $pairCount) {
                    $isCorrect = false;
                }
            }
            }
            // Handle Ordering
            else if ($question->type === 'ordering') {
                $studentOrder = $ans['ordering_answers'] ?? [];
                $correctOrder = $question->options()->orderBy('id', 'asc')->pluck('option_text')->toArray();
                
                $isCorrect = true;
                if (count($studentOrder) !== count($correctOrder)) {
                    $isCorrect = false;
                } else {
                    foreach ($correctOrder as $i => $correctVal) {
                        if (trim($studentOrder[$i] ?? '') !== trim($correctVal)) {
                            $isCorrect = false;
                            break;
                        }
                    }
                }
            }
            // Handle Highlight (evaluated like word selection)
            else if ($question->type === 'highlight') {
                $studentSelected = $ans['highlight_answers'] ?? [];
                if (is_string($studentSelected)) {
                    $studentSelected = json_decode($studentSelected, true) ?? [];
                }

                if (is_array($studentSelected)) {
                    $correctOptions = $question->options()->where('is_correct', true)->pluck('option_text')->toArray();
                    $incorrectOptions = $question->options()->where('is_correct', false)->pluck('option_text')->toArray();
                    
                    $allCorrectSelected = true;
                    foreach ($correctOptions as $correct) {
                        if (!in_array($correct, $studentSelected)) {
                            $allCorrectSelected = false;
                            break;
                        }
                    }
                    
                    $anyIncorrectSelected = false;
                    foreach ($studentSelected as $selected) {
                        if (in_array($selected, $incorrectOptions)) {
                            $anyIncorrectSelected = true;
                            break;
                        }
                    }
                    
                    $isCorrect = $allCorrectSelected && !$anyIncorrectSelected && count($studentSelected) === count($correctOptions);
                }
            }
            // Handle Text answers (Legacy Gap Fill, etc.)
            else if ($question->type !== 'mcq' && isset($ans['text_answer'])) {
                $correctText = $question->options()->where('is_correct', true)->value('option_text');
                $isCorrect = trim(strtolower($ans['text_answer'] ?? '')) === trim(strtolower($correctText ?? ''));
            }

            // Track result for "Exit Point" detection
            $resultsMap[$question->id] = $isCorrect;

            // Handle Audio Upload for Speaking tasks (if applicable)
            $mediaPath = null;
            if ($request->hasFile("answers.{$index}.audio_file")) {
                $file = $request->file("answers.{$index}.audio_file");
                $mediaPath = $file->store("attempts/{$attempt->id}/answers", 'public');
            }

            if ($isCorrect) {
                $earnedPoints += $question->points;
            }

            // --- NEW: Serialize complex answers for storage ---
            $textAnswer = $ans['text_answer'] ?? null;
            if (in_array($question->type, ['word_selection', 'click_word'])) {
                $textAnswer = json_encode($ans['selected_words'] ?? []);
            } else if ($question->type === 'drag_drop') {
                $textAnswer = json_encode($ans['drag_drop_answers'] ?? []);
            } else if ($question->type === 'fill_blank') {
                $textAnswer = json_encode($ans['fill_blank_answers'] ?? []);
            } else if ($question->type === 'matching') {
                $textAnswer = is_string($ans['matching_answers'] ?? null) ? $ans['matching_answers'] : json_encode($ans['matching_answers'] ?? []);
            } else if ($question->type === 'ordering') {
                $textAnswer = json_encode($ans['ordering_answers'] ?? []);
            } else if ($question->type === 'highlight') {
                $textAnswer = json_encode($ans['highlight_answers'] ?? []);
            }

            StudentAnswer::updateOrCreate(
                ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'option_id' => $ans['option_id'] ?? null,
                    'text_answer' => $textAnswer,
                    'media_answer' => $mediaPath,
                    'is_correct' => $isCorrect,
                    'points_awarded' => $isCorrect ? $question->points : 0,
                ]
            );
        }

        $batchScore = $totalPossiblePoints > 0 ? round(($earnedPoints / $totalPossiblePoints) * 100, 1) : 0;
        $passThreshold = $level->pass_threshold ?? 70;
        $passed = $batchScore >= $passThreshold;

        $answeredIds = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->pluck('question_id')
            ->toArray();

        $remainingQuestionsCount = Question::where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->whereNotIn('id', $answeredIds)
            ->count();

        // --- Log Level Performance (Movement Log) ---
        ExamAttemptLevel::create([
            'exam_attempt_id' => $attempt->id,
            'skill_id' => $skillId,
            'level_number' => $levelNum,
            'score' => $batchScore,
            'status' => $passed ? 'passed' : 'failed',
        ]);

        // --- Calculate Aggregate Skill Score (Sum of Max Score per Level) ---
        // Since each level is out of 100 and there are 9 levels, the total is out of 900.
        $totalSkillScore = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->groupBy('level_number')
            ->selectRaw('max(score) as max_score')
            ->get()
            ->sum('max_score');

        // --- Determine the student's exam mode ---
        $student = $attempt->student;          // relationship: ExamAttempt->student
        $isAdaptive = true; // Default to adaptive
        if ($student) {
            $isAdaptive = !$student->not_adaptive;    // not_adaptive = 1 means NOT adaptive
        }

        $nextPos = $pos;
        $finishedExam = false;
        $skillEnded = false;
        $placementLevel = null;   // For not-adaptive: the level where student stopped
        $placementScore = $totalSkillScore; // Use the aggregate score for final report

        // Check if another level exists at all
        $nextLevelExists = Level::where('skill_id', $skillId)
            ->where('level_number', $levelNum + 1)
            ->exists();

        if ($isAdaptive) {
            // ===== ADAPTIVE MODE =====
            // Continue upward through all levels, regardless of passing or failing.
            // Stop only when there are no more levels.
            if ($remainingQuestionsCount == 0) {
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
                            'score' => $totalSkillScore,
                            'status' => 'completed', // They finished all levels
                            'finished_at' => now(),
                        ]
                    );
                }
            } else {
                // More questions left in this level, stay here
                $nextPos['current_level'] = $levelNum;
            }
        } else {
            // ===== NOT-ADAPTIVE MODE =====
            // FAIL → check if retry is allowed
            // PASS → move to next level (if any), otherwise skill is done.
            if (!$passed) {
                // If student has retry permission AND the level allows retry, allow them to stay on the same level
                // Note: getNextBatch automatically excludes answered questions, so they will get a fresh batch.
                if ($student->allows_retry && $level->allows_retry) {
                    $skillEnded = false;
                    $nextPos['current_level'] = $levelNum; // Stay on current level to retry
                } else {
                    $skillEnded = true;
                    $placementLevel = $levelNum;
                    $placementScore = $totalSkillScore;

                    // --- NEW: Find the FIRST wrong question in this specific batch to record as the exit cause ---
                    $firstWrongQuestionId = null;
                    
                    // $resultsMap was built during the evaluation loop above
                    foreach ($request->answers as $ans) {
                        $qid = $ans['question_id'];
                        if (isset($resultsMap[$qid]) && !$resultsMap[$qid]) {
                            $firstWrongQuestionId = $qid;
                            break;
                        }
                    }

                    if ($firstWrongQuestionId) {
                        $attempt->update(['last_seen_question_id' => $firstWrongQuestionId]);
                    } else if (count($request->answers) > 0) {
                        $attempt->update(['last_seen_question_id' => end($request->answers)['question_id']]);
                    }

                    $attempt->attemptSkills()->updateOrCreate(
                        ['skill_id' => $skillId],
                        [
                            'max_level_reached' => $levelNum,
                            'score' => $totalSkillScore,
                            'status' => 'failed',
                            'placement_level' => max($levelNum - 1, 1),
                            'placement_score' => $totalSkillScore,
                            'finished_at' => now(),
                        ]
                    );
                }
            } elseif ($passed) {
                // Check if this was a pass on a SECOND attempt (retry)
                // If they failed once before at this same level, it's a retry pass.
                $previousFailCount = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                    ->where('skill_id', $skillId)
                    ->where('level_number', $levelNum)
                    ->where('status', 'failed')
                    ->count();

                if ($previousFailCount > 0) {
                    // Student PASSED on second attempt → Stop skill as per request
                    $skillEnded = true;
                    $placementLevel = $levelNum;
                    $placementScore = $totalSkillScore;

                    $attempt->attemptSkills()->updateOrCreate(
                        ['skill_id' => $skillId],
                        [
                            'max_level_reached' => $levelNum,
                            'score' => $totalSkillScore,
                            'status' => 'completed',
                            'placement_level' => $levelNum,
                            'placement_score' => $totalSkillScore,
                            'finished_at' => now(),
                        ]
                    );
                } elseif ($remainingQuestionsCount == 0) {
                    if ($nextLevelExists) {
                        // Student PASSED on first attempt and there is a higher level → advance
                        $nextPos['current_level'] = $levelNum + 1;
                    } else {
                        // Student PASSED the final level on first attempt → skill done
                        $skillEnded = true;
                        $placementLevel = $levelNum;
                        $placementScore = $totalSkillScore;

                        $attempt->attemptSkills()->updateOrCreate(
                            ['skill_id' => $skillId],
                            [
                                'max_level_reached' => $levelNum,
                                'score' => $totalSkillScore,
                                'status' => 'completed',
                                'placement_level' => $levelNum,
                                'placement_score' => $totalSkillScore,
                                'finished_at' => now(),
                            ]
                        );
                    }
                }
            }
        } // Close else for non-adaptive mode

        // Move to next skill or finish exam
        if ($skillEnded) {
            if ($pos['current_skill_index'] < count($pos['skill_ids']) - 1) {
                $nextPos['current_skill_index']++;
                $nextPos['current_level'] = 1;
                $nextPos['current_skill_started_at'] = null; // Timer should only start when they launch the next skill
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
            'placement_level' => $placementLevel,
            'placement_score' => $placementScore,
            'is_adaptive' => $isAdaptive,
            // retry_attempt: true triggers the "Second Chance" notification on the frontend
            'retry_attempt' => (!$passed && !$skillEnded && !$isAdaptive),
            'next_step' => $finishedExam ? 'results' : ($skillEnded ? 'dashboard' : 'next_batch'),
        ]);
    }

    /**
     * Update the last seen question ID for an attempt.
     */
    public function updateProgress(Request $request, ExamAttempt $attempt)
    {
        $request->validate([
            'question_id' => 'required|exists:questions,id'
        ]);

        if ($attempt->status !== 'ongoing') {
            return response()->json(['error' => 'Exam is not active.'], 403);
        }

        $attempt->update(['last_seen_question_id' => $request->question_id]);

        return response()->json(['success' => true]);
    }

    /**
     * Get the final results summary for an attempt.
     */
    public function results(ExamAttempt $attempt)
    {
        $attempt->load(['attemptSkills.skill']);
        
        $results = $attempt->attemptSkills->map(function ($as) {
            return [
                'name' => $as->skill->name,
                'level' => $as->max_level_reached,
                'score' => $as->score,
            ];
        });

        return response()->json([
            'skill_results' => $results
        ]);
    }

    // --- Private Helper Methods for Batch Generation ---

    private function fetchStandaloneBatch($examId, $attemptId, $skillId, $level, $qty)
    {
        $query = Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->whereNull('passage_id')
            ->leftJoin('student_answers', function($join) use ($attemptId) {
                $join->on('questions.id', '=', 'student_answers.question_id')
                     ->where('student_answers.exam_attempt_id', '=', $attemptId);
            })
            ->whereNull('student_answers.id')
            ->with('options')
            ->select('questions.*');

        if ($level->is_random) {
            $query->inRandomOrder();
        } else {
            $query->orderBy('questions.sort_order', 'asc')->orderBy('questions.id', 'asc');
        }

        return $query->take($qty)->get();
    }

    private function fetchPassageBatch($examId, $attemptId, $skillId, $level, $qty)
    {
        // Fetch IDs of passages already touched by this student (very small array compared to question IDs)
        $answeredPassageIds = StudentAnswer::where('exam_attempt_id', $attemptId)
            ->join('questions', 'questions.id', '=', 'student_answers.question_id')
            ->whereNotNull('questions.passage_id')
            ->distinct()
            ->pluck('questions.passage_id')
            ->toArray();

        $passageQuery = Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->whereNotNull('passage_id')
            ->whereNotIn('passage_id', $answeredPassageIds)
            ->select('questions.passage_id')
            ->distinct();

        if ($level->is_random) {
            $passageQuery->inRandomOrder();
        } else {
            $passageQuery->orderBy('questions.passage_id', 'asc');
        }

        $ids = $passageQuery->take($qty)->pluck('questions.passage_id')->toArray();
        if (empty($ids)) return collect();

        return Question::where('exam_id', $examId)
            ->whereIn('passage_id', $ids)
            ->leftJoin('student_answers', function($join) use ($attemptId) {
                $join->on('questions.id', '=', 'student_answers.question_id')
                     ->where('student_answers.exam_attempt_id', '=', $attemptId);
            })
            ->whereNull('student_answers.id')
            ->with(['options', 'passage'])
            ->orderBy('questions.sort_order', 'asc')
            ->orderBy('questions.id', 'asc')
            ->select('questions.*')
            ->get();
    }

    private function fetchLegacyBatch($examId, $attemptId, $skillId, $level, $qty)
    {
        $query = Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->leftJoin('student_answers', function($join) use ($attemptId) {
                $join->on('questions.id', '=', 'student_answers.question_id')
                     ->where('student_answers.exam_attempt_id', '=', $attemptId);
            })
            ->whereNull('student_answers.id')
            ->with(['options', 'passage'])
            ->select('questions.*');

        if ($level->is_random) {
            $query->inRandomOrder();
        } else {
            $query->orderBy('questions.sort_order', 'asc')->orderBy('questions.id', 'asc');
        }

        $ruleQuestions = $query->take($qty)->get();
        $pIds = $ruleQuestions->whereNotNull('passage_id')->pluck('passage_id')->unique()->toArray();

        if (empty($pIds)) return $ruleQuestions;

        $allPassageGrouped = Question::where('exam_id', $examId)
            ->whereIn('passage_id', $pIds)
            ->leftJoin('student_answers', function($join) use ($attemptId) {
                $join->on('questions.id', '=', 'student_answers.question_id')
                     ->where('student_answers.exam_attempt_id', '=', $attemptId);
            })
            ->whereNull('student_answers.id')
            ->with(['options', 'passage'])
            ->orderBy('questions.sort_order', 'asc')
            ->orderBy('questions.id', 'asc')
            ->select('questions.*')
            ->get()
            ->groupBy('passage_id');

        $final = collect();
        $done = [];
        foreach ($ruleQuestions as $q) {
            if ($q->passage_id && !in_array($q->passage_id, $done)) {
                $done[] = $q->passage_id;
                $final = $final->concat($allPassageGrouped->get($q->passage_id, collect()));
            } elseif (!$q->passage_id) {
                $final->push($q);
            }
        }
        return $final;
    }

    /**
     * Helper to find the first valid level number that has questions if the requested level is empty
     */
    private function getValidStartingLevel($examId, $skillId, $requestedLevelNumber)
    {
        // 1. Resolve the requested level_number to its actual Level ID in the DB
        $requestedLevel = \App\Models\Level::where('skill_id', $skillId)
            ->where('level_number', $requestedLevelNumber)
            ->first();

        if ($requestedLevel) {
            $hasQuestions = \App\Models\Question::where('exam_id', $examId)
                ->where('skill_id', $skillId)
                ->where('level_id', $requestedLevel->id)
                ->exists();
                
            if ($hasQuestions) {
                return $requestedLevelNumber;
            }
        }
        
        // 2. Find the first level ID that actually has questions for this skill
        $firstValidLevelId = \App\Models\Question::where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->whereNotNull('level_id')
            ->orderBy('level_id', 'asc')
            ->value('level_id');
            
        if ($firstValidLevelId) {
            $validLevelNum = \App\Models\Level::where('id', $firstValidLevelId)->value('level_number');
            if ($validLevelNum) {
                return $validLevelNum;
            }
        }
            
        return $requestedLevelNumber;
    }

    /**
     * Reset exam progress for demo users
     */
    public function resetDemo(Request $request, Exam $exam)
    {
        $user = $request->user();
        if (!in_array(strtolower($user->role), ['demo', 'deom', 'staff'])) {
            return response()->json(['error' => 'Unauthorized. Only demo accounts can perform this action.'], 403);
        }

        \App\Models\ExamAttempt::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->delete();

        return response()->json(['message' => 'Demo progress reset successfully']);
    }
}
