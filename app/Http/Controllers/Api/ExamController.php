<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptLevel;
use App\Models\ExamAttemptSkill;
use App\Models\Level;
use App\Models\Question;
use App\Models\Skill;
use App\Models\StudentAnswer;
use App\Models\StudentExamConfig;
use App\Models\User;
use App\Notifications\SkillCompletedNotification;
use App\Services\AttemptService;
use App\Services\ExamService;
use App\Services\QuestionService;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Notification;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamService $examService,
        private readonly QuestionService $questionService,
        private readonly ScoringService $scoringService,
        private readonly AttemptService $attemptService,
    ) {
    }

    // =========================================================================
    // 1. LIST EXAMS
    // =========================================================================

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return response()->json(Exam::with('category', 'skills')->get());
        }

        if ($this->examService->isDemoUser($user)) {
            return response()->json($this->buildDemoExamList($user));
        }

        $studentProfile = $user->student;
        if (!$studentProfile) {
            return response()->json([]);
        }

        $assignedExamIds = $studentProfile->configs()->pluck('exam_id')->toArray();

        if ($studentProfile->package && $studentProfile->package->exam_id) {
            if (!in_array($studentProfile->package->exam_id, $assignedExamIds)) {
                $assignedExamIds[] = $studentProfile->package->exam_id;
            }
        }

        $exams = Exam::whereIn('id', $assignedExamIds)->with(['language', 'skills'])->get();

        $allowedSkillIdentifiers = $this->examService->getAllowedSkills($studentProfile);

        // Pre-load attempts and completed skills in 2 queries (no N+1)
        $latestAttempts = ExamAttempt::where('student_id', $studentProfile->id)
            ->whereIn('exam_id', $assignedExamIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('exam_id')
            ->map(fn($g) => $g->first());

        $attemptIds = $latestAttempts->pluck('id')->filter()->toArray();
        $completedSkillsByAttempt = ExamAttemptSkill::whereIn('exam_attempt_id', $attemptIds)
            ->whereIn('status', ['completed', 'failed'])
            ->get()
            ->groupBy('exam_attempt_id')
            ->map(fn($g) => $g->pluck('skill_id')->unique()->values());

        $exams->each(function ($exam) use ($allowedSkillIdentifiers, $latestAttempts, $completedSkillsByAttempt) {
            if (!empty($allowedSkillIdentifiers)) {
                $exam->setRelation(
                    'skills',
                    $this->examService->filterSkills($exam->skills, $allowedSkillIdentifiers)
                );
            }

            $exam->latest_attempt = $latestAttempts->get($exam->id);
            $exam->completed_skill_ids = $exam->latest_attempt
                ? $completedSkillsByAttempt->get($exam->latest_attempt->id, collect())->values()
                : [];
        });

        return response()->json($exams);
    }

    // =========================================================================
    // 2. START / RESUME EXAM
    // =========================================================================

    public function start(Request $request, Exam $exam)
    {
        $user = $request->user();
        $isDemo = $this->examService->isDemoUser($user);
        $studentProfile = $user->student;

        if (!$studentProfile && !$isDemo) {
            return response()->json(['error' => 'Student profile not found.'], 404);
        }

        // Block repeated skill attempts for real students
        if ($request->has('skill_id') && !$isDemo) {
            $requestedSkillId = (int) $request->skill_id;
            $hasCompletedSkill = ExamAttemptSkill::whereHas(
                'attempt',
                fn($q) =>
                $q->where('student_id', $studentProfile->id)->where('exam_id', $exam->id)
            )->where('skill_id', $requestedSkillId)
                ->whereIn('status', ['completed', 'failed'])
                ->exists();

            if ($hasCompletedSkill) {
                return response()->json(['error' => 'You have already completed the evaluation for this specific module.'], 403);
            }
        }

        $ownerKey = $isDemo ? 'user_id' : 'student_id';
        $ownerId = $isDemo ? $user->id : $studentProfile->id;

        $attempt = ExamAttempt::where($ownerKey, $ownerId)
            ->where('exam_id', $exam->id)
            ->where('status', 'ongoing')
            ->first();

        if ($attempt) {
            $attempt = $this->handleResumeAttempt($request, $attempt, $exam, $user, $isDemo);
        } else {
            $attempt = $this->createNewAttempt($request, $exam, $user, $studentProfile, $isDemo);
            if (!$attempt) {
                return response()->json(['error' => 'This exam has not been formally assigned to your account or package.'], 403);
            }
            if ($attempt === 'no_skills') {
                return response()->json(['error' => 'No skills have been activated for your exam attempt. Please contact an administrator.'], 422);
            }
        }

        return response()->json([
            'attempt' => $attempt->load('exam'),
            'assigned_skills' => Skill::whereIn('id', $attempt->current_position['skill_ids'])->get(),
        ]);
    }

    // =========================================================================
    // 3. GET NEXT BATCH
    // =========================================================================

    public function getNextBatch(Request $request, ExamAttempt $attempt, int $retryCount = 0)
    {
        if ($retryCount > 10) {
            return response()->json(['error' => 'Infinite recursion detected. Please ensure your exam has questions assigned.'], 500);
        }

        $user = $request->user();
        Log::info("getNextBatch hit for Attempt ID: " . $attempt->id);

        if ($attempt->status !== 'ongoing') {
            return response()->json(['error' => 'Exam is not active.'], 403);
        }

        $pos = $attempt->current_position ?? [];
        if (!isset($pos['skill_ids'][$pos['current_skill_index']])) {
            return response()->json(['error' => 'Exam configuration error: Skill not found.'], 500);
        }

        $skillId = $pos['skill_ids'][$pos['current_skill_index']];
        $levelNum = $pos['current_level'];

        $level = Level::where('skill_id', $skillId)->where('level_number', $levelNum)->first();
        if (!$level) {
            return response()->json(['error' => "Configuration missing for Level {$levelNum}."], 404);
        }

        $questions = $this->questionService->fetchBatchForLevel($attempt->exam_id, $attempt->id, $skillId, $level);

        // Shuffle options
        $questions = $questions->map(function ($q) {
            $q->setRelation('options', $q->options->shuffle());
            return $q;
        });

        if ($questions->isEmpty()) {
            return $this->handleEmptyBatch($request, $attempt, $pos, $skillId, $levelNum, $level, $retryCount);
        }

        // Ensure per-skill timer is firmly locked to the database record
        $skillRecord = \App\Models\ExamAttemptSkill::firstOrCreate(
            ['exam_attempt_id' => $attempt->id, 'skill_id' => $skillId],
            ['started_at' => now()]
        );

        if (empty($pos['current_skill_started_at']) || $pos['current_skill_started_at'] !== $skillRecord->started_at->toIso8601String()) {
            $pos['current_skill_started_at'] = $skillRecord->started_at->toIso8601String();
            $attempt->update(['current_position' => $pos]);
        }

        $skillDuration = DB::table('exam_skill')
            ->where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->value('duration') ?? 0;

        $isDemo = $this->examService->isDemoUser($user);

        return response()->json([
            'skill' => Skill::find($skillId),
            'level' => $level,
            'questions' => $questions,
            'total_questions' => $this->questionService->getTotalLevelQuestions($attempt->exam_id, $skillId, $level->id),
            'timer_type' => $attempt->exam->timer_type ?? 'global',
            'time_limit' => $attempt->exam->time_limit ?? 0,
            'skill_duration' => $isDemo ? 0 : $skillDuration,
            'current_skill_started_at' => $pos['current_skill_started_at'],
            'skill_cheat_warnings' => $skillRecord->cheat_warnings ?? 0,
        ]);
    }

    // =========================================================================
    // 4. SUBMIT BATCH
    // =========================================================================

    public function submitBatch(Request $request, ExamAttempt $attempt)
    {
        Log::info('Submit Batch Request:', $request->all());

        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.option_id' => 'nullable|exists:question_options,id',
            'answers.*.text_answer' => 'nullable|string',
            'answers.*.audio_file' => 'nullable|file|max:20480',
        ]);

        $pos = $attempt->current_position ?? [];
        if (!isset($pos['skill_ids'])) {
            return response()->json(['error' => 'Current position is invalid.'], 500);
        }

        $skillId = $pos['skill_ids'][$pos['current_skill_index']];


        $levelNum = $pos['current_level'];

        $level = Level::where('skill_id', $skillId)->where('level_number', $levelNum)->first();
        if (!$level) {
            return response()->json(['error' => "Level configuration not found for Level {$levelNum}."], 404);
        }

        // ── Grade answers ──────────────────────────────────────────────────────
        $questionIds = collect($request->answers)->pluck('question_id')->unique()->toArray();
        $questionsMap = Question::with('options')->whereIn('id', $questionIds)->get()->keyBy('id');

        $earnedPoints = 0;
        $totalPossiblePoints = 0;
        $resultsMap = [];

        foreach ($request->answers as $index => $ans) {
            $question = $questionsMap->get($ans['question_id']);
            if (!$question)
                continue;

            $totalPossiblePoints += $question->points;
            $pointsAwarded = $this->scoringService->gradeAnswer($question, $ans);
            $isCorrect = $pointsAwarded > 0;
            $resultsMap[$question->id] = $isCorrect;

            $earnedPoints += $pointsAwarded;

            $mediaPath = $this->scoringService->storeAudioFile($request, $attempt->id, $index);
            $textAnswer = $this->scoringService->serializeAnswerForStorage($question, $ans);

            StudentAnswer::updateOrCreate(
                ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'skill_id' => $skillId,
                    'option_id' => $ans['option_id'] ?? null,
                    'text_answer' => $textAnswer,
                    'media_answer' => $mediaPath,
                    'is_correct' => $isCorrect,
                    'points_awarded' => $pointsAwarded,
                ]
            );
        }

        // ── Score & progression ────────────────────────────────────────────────
        $passThreshold = $level->pass_threshold ?? 70;
        $batchScore = $totalPossiblePoints > 0 ? round(($earnedPoints / $totalPossiblePoints) * 100, 1) : 0;
        $passed = $batchScore >= $passThreshold;

        $levelScore = $this->attemptService->computeLevelScore($attempt, $skillId, $level);
        $remainingCount = $this->attemptService->countRemainingQuestions($attempt, $skillId, $level);

        $student = $attempt->student;
        $isAdaptive = $student ? !$student->not_adaptive : true;

        // Determine whether we should log an ExamAttemptLevel entry
        $shouldLog = $isAdaptive ? ($remainingCount === 0) : ($remainingCount === 0 || !$passed);
        if ($shouldLog) {
            $this->attemptService->logLevelResult($attempt, $skillId, $level, $levelScore, $passThreshold);
        }

        $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
        $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);

        $nextLevelExists = $this->attemptService->nextLevelExists($attempt->exam_id, $skillId, $levelNum);
        $nextPos = $pos;
        $skillEnded = false;
        $placementLevel = null;

        [$nextPos, $skillEnded, $placementLevel] = $this->resolveProgression(
            $attempt,
            $pos,
            $level,
            $skillId,
            $levelNum,
            $passed,
            $isAdaptive,
            $remainingCount,
            $nextLevelExists,
            $skillScore,
            $student,
            $request->answers,
            $resultsMap
        );

        // Move to next skill / finish exam
        $finishedExam = false;
        if ($skillEnded) {
            $skill = Skill::find($skillId);
            $admins = User::whereIn('role', ['admin', 'teacher'])->get();
            Notification::send($admins, new SkillCompletedNotification($attempt, $skill));

            $advanced = $this->attemptService->advanceToNextSkillOrFinish($attempt, $nextPos, $skillId);
            $nextPos = $advanced['next_pos'];

            // Check if all assigned skills have been completed
            $allCompleted = true;
            foreach ($pos['skill_ids'] as $id) {
                if (!in_array($id, $nextPos['completed_skills'])) {
                    $allCompleted = false;
                    break;
                }
            }

            if ($allCompleted || $advanced['finished_exam']) {
                // Wait, if $advanced['finished_exam'] is true but $allCompleted is false, we should NOT finish the exam!
                // Actually, if we reached the end of the array, we might want to loop back to the first incomplete skill.
                // For now, if they are sent to the dashboard, the dashboard handles letting them pick the next incomplete skill.
                // We just need to make sure we don't lock the exam attempt.
                $finishedExam = $allCompleted;
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
            'placement_score' => $skillScore,
            'is_adaptive' => $isAdaptive,
            'retry_attempt' => (!$passed && !$skillEnded && !$isAdaptive),
            'next_step' => $finishedExam ? 'results' : ($skillEnded ? 'dashboard' : 'next_batch'),
        ]);
    }

    // =========================================================================
    // 5. MISC PUBLIC ENDPOINTS
    // =========================================================================

    public function updateProgress(Request $request, ExamAttempt $attempt)
    {
        $request->validate(['question_id' => 'required|exists:questions,id']);

        if ($attempt->status !== 'ongoing') {
            return response()->json(['error' => 'Exam is not active.'], 403);
        }

        $attempt->update(['last_seen_question_id' => $request->question_id]);

        return response()->json(['success' => true]);
    }

    public function timeout(ExamAttempt $attempt)
    {
        if ($attempt->status === 'ongoing') {
            $pos = $attempt->current_position ?? [];
            if (!empty($pos['skill_ids']) && isset($pos['current_skill_index'])) {
                $skillId = $pos['skill_ids'][$pos['current_skill_index']];

                // Get current score for the skill
                $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
                $maxLevel = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                    ->where('skill_id', $skillId)
                    ->max('level_number') ?? 1;

                // Finalize the specific skill
                $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $maxLevel, 'completed');

                // Update overall score
                $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);

                // Advance to next skill internally, so the global attempt can finish if needed
                $advanced = $this->attemptService->advanceToNextSkillOrFinish($attempt, $pos, $skillId);

                // Check if all assigned skills have been completed
                $allCompleted = true;
                foreach ($pos['skill_ids'] as $id) {
                    if (!in_array($id, $advanced['next_pos']['completed_skills'])) {
                        $allCompleted = false;
                        break;
                    }
                }

                if ($allCompleted) {
                    $attempt->update(['status' => 'completed', 'finished_at' => now(), 'current_position' => $advanced['next_pos']]);
                } else {
                    $attempt->update(['current_position' => $advanced['next_pos']]);
                }
            } else {
                // Fallback
                $attempt->update(['status' => 'completed', 'finished_at' => now()]);
            }
        }

        return response()->json(['success' => true, 'next_step' => 'dashboard']);
    }

    public function finish(ExamAttempt $attempt)
    {
        if ($attempt->status === 'ongoing') {
            $admins = \App\Models\User::whereIn('role', ['admin', 'teacher'])->get();
            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\ExamExitedNotification($attempt));

            $pos = $attempt->current_position ?? [];
            if (!empty($pos['skill_ids']) && isset($pos['current_skill_index'])) {
                $skillId = $pos['skill_ids'][$pos['current_skill_index']];

                // Finalize the current skill as incomplete if needed
                $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
                $maxLevel = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                    ->where('skill_id', $skillId)
                    ->max('level_number') ?? 1;

                $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $maxLevel, 'completed');
                $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);
            }
        }

        return response()->json(['success' => true]);
    }

    public function results(ExamAttempt $attempt)
    {
        $attempt->load(['attemptSkills.skill']);

        $results = $attempt->attemptSkills->map(fn($as) => [
            'name' => $as->skill->name,
            'level' => $as->max_level_reached,
            'score' => $as->score,
        ]);

        return response()->json(['skill_results' => $results]);
    }

    public function resetDemo(Request $request, Exam $exam)
    {
        $user = $request->user();

        if (!$this->examService->isDemoUser($user)) {
            return response()->json(['error' => 'Unauthorized. Only demo accounts can perform this action.'], 403);
        }

        ExamAttempt::where('user_id', $user->id)->where('exam_id', $exam->id)->delete();

        return response()->json(['message' => 'Demo progress reset successfully']);
    }

    public function logWarning(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->status !== 'ongoing') {
            return response()->json(['error' => 'Exam is not active.'], 403);
        }

        // Per-skill increment (Specific warnings for this skill)
        $pos = $attempt->current_position ?? [];
        $currentWarnings = 0;
        $shouldTerminateSkill = false;

        if (isset($pos['skill_ids'][$pos['current_skill_index']])) {
            $skillId = $pos['skill_ids'][$pos['current_skill_index']];
            
            $skillAttempt = ExamAttemptSkill::firstOrCreate(
                ['exam_attempt_id' => $attempt->id, 'skill_id' => $skillId],
                ['started_at' => now(), 'status' => 'in_progress']
            );
            $skillAttempt->increment('cheat_warnings');
            $currentWarnings = $skillAttempt->cheat_warnings;

            // NEW: If warnings reach 3, terminate this skill specifically
            if ($currentWarnings >= 3) {
                $shouldTerminateSkill = true;
                
                // Finalize the skill as 'failed' due to cheating
                $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
                $maxLevel = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                    ->where('skill_id', $skillId)
                    ->max('level_number') ?? 1;

                $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $maxLevel, 'failed');
                $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);
                
                // Advance position to next skill so they can't continue this one
                $advanced = $this->attemptService->advanceToNextSkillOrFinish($attempt, $pos, $skillId);
                $attempt->update(['current_position' => $advanced['next_pos']]);
                
                if ($advanced['finished_exam']) {
                    $attempt->update(['status' => 'completed', 'finished_at' => now()]);
                }
            }
        }
        
        return response()->json([
            'success' => true, 
            'warnings' => $currentWarnings,
            'should_terminate_skill' => $shouldTerminateSkill
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function buildDemoExamList($user): array
    {
        $exams = Exam::with(['category', 'skills'])->get();
        $examIds = $exams->pluck('id')->toArray();

        $demoAttempts = ExamAttempt::where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('exam_id')
            ->map(fn($g) => $g->first());

        $exams->each(function ($exam) use ($demoAttempts) {
            $exam->latest_attempt = $demoAttempts->get($exam->id);
            $exam->completed_skill_ids = [];
        });

        return $exams->toArray();
    }

    private function handleResumeAttempt(Request $request, ExamAttempt $attempt, Exam $exam, $user, bool $isDemo): ExamAttempt
    {
        if (!$request->has('skill_id')) {
            return $attempt;
        }

        $requestedSkillId = (int) $request->skill_id;
        $pos = $attempt->current_position;
        $skillIndex = array_search($requestedSkillId, $pos['skill_ids']);

        if ($skillIndex === false) {
            return $attempt;
        }

        if ($isDemo) {
            $isFinished = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $requestedSkillId)
                ->whereIn('status', ['completed', 'failed'])
                ->exists();

            $requestedLevel = $request->has('level_id')
                ? $this->questionService->getValidStartingLevel($exam->id, $requestedSkillId, (int) $request->level_id)
                : 1;

            if ($isFinished) {
                $attempt->update(['status' => 'completed', 'finished_at' => now()]);
                $attempt = ExamAttempt::create([
                    'user_id' => $user->id,
                    'exam_id' => $exam->id,
                    'status' => 'ongoing',
                    'current_position' => [
                        'skill_ids' => $pos['skill_ids'],
                        'current_skill_index' => $skillIndex,
                        'current_level' => $requestedLevel,
                        'current_skill_started_at' => null,
                    ],
                ]);
                return $attempt;
            }

            // Clear previous answers for this skill so they can retry
            StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->whereHas('question', fn($q) => $q->where('skill_id', $requestedSkillId))
                ->delete();

            ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $requestedSkillId)
                ->delete();

            $pos['current_level'] = $requestedLevel;
        }

        if ($pos['current_skill_index'] !== $skillIndex) {
            // Retrieve existing skill record if it was previously started
            $existingSkill = \App\Models\ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $requestedSkillId)
                ->first();

            $pos['current_skill_started_at'] = $existingSkill && $existingSkill->started_at ? $existingSkill->started_at->toIso8601String() : null;

            if (!$isDemo) {
                // Determine the highest level they reached in this skill to resume from
                $maxLevel = \App\Models\ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                    ->where('skill_id', $requestedSkillId)
                    ->max('level_number');

                $pos['current_level'] = $maxLevel ? $maxLevel : 1;
            }
        }

        $pos['current_skill_index'] = $skillIndex;
        $attempt->update(['current_position' => $pos]);

        return $attempt;
    }

    private function createNewAttempt(Request $request, Exam $exam, $user, $studentProfile, bool $isDemo): ExamAttempt|string|null
    {
        if (!$isDemo) {
            $config = StudentExamConfig::where('student_id', $studentProfile->id ?? 0)
                ->where('exam_id', $exam->id)
                ->first();

            if (!$config) {
                $config = \App\Models\Student::assignDefaultExam($studentProfile, $exam->id);
                if (!$config) {
                    return null;
                }
            }
        } else {
            $config = null;
        }

        $allowedSkillIdentifiers = $this->examService->getAllowedSkills($studentProfile);

        if (empty($allowedSkillIdentifiers)) {
            $allowedSkillIdentifiers = $exam->skills->pluck('name')->toArray();
        }

        $assignedSkills = [];
        foreach ($exam->skills as $skill) {
            $skillName = strtolower(trim($skill->name));

            if (!empty($allowedSkillIdentifiers)) {
                $shouldInclude = $this->examService->skillMatchesIdentifiers($skill, $allowedSkillIdentifiers);
            } elseif ($config) {
                $shouldInclude = ($skillName === 'listening' && $config->want_listening)
                    || (in_array($skillName, ['reading', 'reading comprehension']) && $config->want_reading)
                    || (in_array($skillName, ['grammar', 'structure']) && $config->want_grammar)
                    || ($skillName === 'writing' && $config->want_writing)
                    || ($skillName === 'speaking' && $config->want_speaking);
            } else {
                $shouldInclude = true;
            }

            if ($shouldInclude) {
                $assignedSkills[] = $skill->id;
            }
        }

        if (empty($assignedSkills)) {
            return 'no_skills';
        }

        $startIndex = 0;
        if ($request->has('skill_id')) {
            $found = array_search((int) $request->skill_id, $assignedSkills);
            if ($found !== false) {
                $startIndex = $found;
            }
        }

        $startingLevel = $this->questionService->getValidStartingLevel(
            $exam->id,
            $assignedSkills[$startIndex] ?? 0,
            ($isDemo && $request->has('level_id')) ? (int) $request->level_id : 1
        );

        return ExamAttempt::create([
            'student_id' => $studentProfile?->id,
            'user_id' => $isDemo ? $user->id : null,
            'exam_id' => $exam->id,
            'status' => 'ongoing',
            'current_position' => [
                'skill_ids' => $assignedSkills,
                'current_skill_index' => $startIndex,
                'current_level' => $startingLevel,
                'completed_skills' => [],
                'current_skill_started_at' => null,
            ],
        ]);
    }

    private function handleEmptyBatch(Request $request, ExamAttempt $attempt, array $pos, int $skillId, int $levelNum, Level $level, int $retryCount)
    {
        $user = $request->user();
        $isDemo = $this->examService->isDemoUser($user);

        if ($isDemo && $levelNum > 1) {
            Log::warning("Demo: end of questions at Level {$levelNum}. Resetting to Level 1.");

            StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->whereHas('question', fn($q) => $q->where('skill_id', $skillId))
                ->delete();

            ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $skillId)
                ->delete();

            $pos['current_level'] = 1;
            $attempt->update(['current_position' => $pos]);

            return $this->getNextBatch($request, $attempt, $retryCount + 1);
        }

        if ($isDemo && $levelNum === 1) {
            $skills = $attempt->exam->skills()->orderBy('id')->get();
            $nextSkillIndex = $pos['current_skill_index'] + 1;

            if ($nextSkillIndex < $skills->count()) {
                Log::info("Demo Level 1 empty for Skill Index {$pos['current_skill_index']}. Auto-advancing.");
                $pos['current_skill_index'] = $nextSkillIndex;
                $pos['current_level'] = 1;
                $attempt->update(['current_position' => $pos]);

                return $this->getNextBatch($request, $attempt, $retryCount + 1);
            }
        }

        return response()->json([
            'error' => "Empty Question Set: No questions found for level '{$level->id}' (Skill ID: {$skillId}).",
            'is_empty' => true,
            'debug' => [
                'skill_id' => $skillId,
                'id' => $level->id,
                'attempt_id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
            ],
        ], 404);
    }

    /**
     * Resolve the next position and skill-end state based on adaptive/non-adaptive mode.
     *
     * @return array{0: array, 1: bool, 2: int|null}  [nextPos, skillEnded, placementLevel]
     */
    private function resolveProgression(
        ExamAttempt $attempt,
        array $pos,
        Level $level,
        int $skillId,
        int $levelNum,
        bool $passed,
        bool $isAdaptive,
        int $remainingCount,
        bool $nextLevelExists,
        float $skillScore,
        $student,
        array $rawAnswers,
        array $resultsMap
    ): array {
        $nextPos = $pos;
        $skillEnded = false;
        $placementLevel = null;

        if ($isAdaptive) {
            if ($remainingCount === 0) {
                if ($nextLevelExists) {
                    $nextPos['current_level'] = $levelNum + 1;
                } else {
                    $skillEnded = true;
                    $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $levelNum, 'completed');
                }
            } else {
                $nextPos['current_level'] = $levelNum;
            }
        } else {
            // Non-adaptive
            if (!$passed) {
                if ($student?->allows_retry && $level->allows_retry) {
                    $nextPos['current_level'] = $levelNum; // stay for retry
                } else {
                    $skillEnded = true;
                    $placementLevel = $levelNum;
                    $this->recordExitQuestion($attempt, $rawAnswers, $resultsMap);
                    $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $levelNum, 'failed', max($levelNum - 1, 1));
                }
            } elseif ($passed) {
                if ($this->attemptService->hasPreviousFailure($attempt, $skillId, $levelNum)) {
                    $skillEnded = true;
                    $placementLevel = $levelNum;
                    $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $levelNum, 'completed', $levelNum);
                } elseif ($remainingCount === 0) {
                    if ($nextLevelExists) {
                        $nextPos['current_level'] = $levelNum + 1;
                    } else {
                        $skillEnded = true;
                        $placementLevel = $levelNum;
                        $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $levelNum, 'completed', $levelNum);
                    }
                }
            }
        }

        return [$nextPos, $skillEnded, $placementLevel];
    }

    private function recordExitQuestion(ExamAttempt $attempt, array $rawAnswers, array $resultsMap): void
    {
        $firstWrongId = null;
        foreach ($rawAnswers as $ans) {
            $qid = $ans['question_id'];
            if (isset($resultsMap[$qid]) && !$resultsMap[$qid]) {
                $firstWrongId = $qid;
                break;
            }
        }

        $lastId = $firstWrongId ?? (count($rawAnswers) > 0 ? end($rawAnswers)['question_id'] : null);

        if ($lastId) {
            $attempt->update(['last_seen_question_id' => $lastId]);
        }
    }
}
