<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExamProgress\SubmitAnswersRequest;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptLevel;
use App\Models\ExamAttemptSkill;
use App\Models\Level;
use App\Models\Question;
use App\Models\Skill;
use App\Models\StudentAnswer;
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

class ExamProgressController extends Controller
{
    public function __construct(
        private readonly ExamService $examService,
        private readonly QuestionService $questionService,
        private readonly ScoringService $scoringService,
        private readonly AttemptService $attemptService,
    ) {
    }

    public function getNextBatch(Request $request, ExamAttempt $attempt, int $retryCount = 0)
    {
        $this->authorize('view', $attempt);

        if ($retryCount > 10) {
            return response()->json(['error' => 'Infinite recursion detected.'], 500);
        }

        if ($attempt->status !== 'ongoing') {
            return response()->json(['error' => 'Exam is not active.'], 403);
        }

        $attempt->loadMissing('exam.skills');

        $pos = $attempt->current_position ?? [];

        if (!isset($pos['skill_ids'][$pos['current_skill_index']])) {
            return response()->json(['error' => 'Skill not found.'], 500);
        }

        $skillId = $pos['skill_ids'][$pos['current_skill_index']];

        /**
         * ✅ أهم نقطة:
         * current_level لازم ييجي من الـ position نفسها
         * أو default = 1
         */
        $levelNum = $pos[(string) $skillId]['current_level'] ?? 1;

        $level = Level::where('skill_id', $skillId)
            ->where('level_number', $levelNum)
            ->first();

        if (!$level) {
            return response()->json(['error' => "Level missing."], 404);
        }

        $questions = $this->questionService->fetchBatchForLevel(
            $attempt->exam_id,
            $attempt->id,
            $skillId,
            $level
        );

        $questions = $questions->map(function ($q) {
            $q->setRelation('options', $q->options->shuffle());
            return $q;
        });

        // ── Speaking-live / writing resume fix ───────────────────────────────
        // These question types require a media file upload (audio/PDF).
        // If the student submitted the batch but the media_answer column is
        // still NULL (e.g. upload failed, or they refreshed before uploading),
        // the answer row exists → `fetchBatchForLevel` returns empty.
        // We detect that case here and re-surface the unanswered media question
        // so the student can re-record / re-upload instead of getting stuck.
        if ($questions->isEmpty()) {
            $mediaTypes = ['speaking', 'speaking_live', 'writing', 'pdf_annotation'];

            $incompleteMediaAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->whereNull('media_answer')
                ->whereHas('question', fn($q) => $q
                    ->where('exam_id', $attempt->exam_id)
                    ->where('skill_id', $skillId)
                    ->where('level_id', $level->id)
                    ->whereIn('type', $mediaTypes))
                ->with(['question.options'])
                ->get();

            if ($incompleteMediaAnswers->isNotEmpty()) {
                // Re-surface questions whose media has not been uploaded yet.
                $questions = $incompleteMediaAnswers
                    ->map(fn($answer) => $answer->question)
                    ->filter()
                    ->unique('id')
                    ->values();
            }
        }
        // ── End speaking-live / writing resume fix ────────────────────────────

        if ($questions->isEmpty()) {
            return $this->handleEmptyBatch(
                $request,
                $attempt,
                $pos,
                $skillId,
                $levelNum,
                $level,
                $retryCount
            );
        }

        $skillRecord = ExamAttemptSkill::firstOrCreate(
            [
                'exam_attempt_id' => $attempt->id,
                'skill_id' => $skillId
            ],
            [
                'started_at' => now(),
                'status' => 'in_progress'
            ]
        );

        if (!$skillRecord->started_at) {
            $skillRecord->update([
                'started_at' => now(),
                'status' => 'in_progress'
            ]);
        }

        // حفظ بداية المهارة فقط
        if (
            empty($pos['current_skill_started_at']) ||
            $pos['current_skill_started_at'] !== $skillRecord->started_at->toIso8601String()
        ) {

            $pos['current_skill_started_at'] = $skillRecord->started_at->toIso8601String();

            $attempt->update([
                'current_position' => $pos
            ]);
        }

        $skillDuration = DB::table('exam_skill')
            ->where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->value('duration') ?? 0;

        $isDemo = $this->examService->isDemoUser($request->user());

        $skillTotalQuestions = 0;
        $skillGlobalOffset = 0;

        $skillLevels = Level::where('skill_id', $skillId)->get();

        foreach ($skillLevels as $lvl) {
            $lvlTotal = $this->questionService->getTotalLevelQuestions($attempt->exam_id, $skillId, $lvl->id);
            $skillTotalQuestions += $lvlTotal;

            if ($lvl->level_number < $levelNum) {
                $skillGlobalOffset += $lvlTotal;
            }
        }

        $returnedStartedAt = $pos['current_skill_started_at'];

        if (isset($pos[(string) $skillId]['resumed_time'])) {
            $returnedStartedAt = $pos[(string) $skillId]['resumed_time'];
            unset($pos[(string) $skillId]['resumed_time']);
            $attempt->update([
                'current_position' => $pos
            ]);
        }

        return response()->json([
            'skill' => $attempt->exam->skills->firstWhere('id', $skillId),
            'level' => $level,
            'questions' => $questions,
            'total_questions' => $this->questionService
                ->getTotalLevelQuestions($attempt->exam_id, $skillId, $level->id),

            'skill_total_questions' => $skillTotalQuestions,
            'skill_global_offset' => $skillGlobalOffset,

            'timer_type' => $attempt->exam->timer_type ?? 'global',
            'time_limit' => $attempt->exam->time_limit ?? 0,
            'skill_duration' => $isDemo ? 0 : $skillDuration,

            'current_skill_started_at' => $returnedStartedAt,
            'skill_cheat_warnings' => $skillRecord->cheat_warnings ?? 0,

            // مهم جداً للتتبع
            'current_level' => $levelNum,
        ]);
    }




    public function submitBatch(SubmitAnswersRequest $request, ExamAttempt $attempt)
    {
        $this->authorize('update', $attempt);

        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($request, $attempt) {

                $attempt = ExamAttempt::where('id', $attempt->id)
                    ->with('exam.skills')
                    ->lockForUpdate()
                    ->first();

                if ($attempt->status !== 'ongoing') {
                    return response()->json(['error' => 'Exam is not active.'], 403);
                }

                $pos = $attempt->current_position ?? [];

                $skillId = $pos['skill_ids'][$pos['current_skill_index']] ?? null;

                if (!$skillId) {
                    return response()->json(['error' => 'Invalid skill position'], 500);
                }

                // ✅ PER-SKILL STATE
                $skillState = $pos[$skillId] ?? [];
                $levelNum = $skillState['current_level'] ?? 1;

                $level = Level::where('skill_id', $skillId)
                    ->where('level_number', $levelNum)
                    ->first();

                if (!$level) {
                    return response()->json(['error' => "Level not found."], 404);
                }

                $questionIds = collect($request->answers)
                    ->pluck('question_id')
                    ->unique()
                    ->toArray();

                $questionsMap = Question::with('options')
                    ->whereIn('id', $questionIds)
                    ->get()
                    ->keyBy('id');

                $earnedPoints = 0;
                $totalPossiblePoints = 0;
                $resultsMap = [];
                $lastQuestionId = null;

                foreach ($request->answers as $index => $ans) {

                    $question = $questionsMap->get($ans['question_id']);

                    if (!$question)
                        continue;

                    $lastQuestionId = $question->id;

                    if (in_array($question->type, ['speaking', 'writing', 'speaking_live', 'pdf_annotation'])) {
                        $pointsAwarded = 0;
                    } else {
                        $totalPossiblePoints += $question->points;
                        $pointsAwarded = $this->scoringService->gradeAnswer($question, $ans);
                        $earnedPoints += $pointsAwarded;
                    }

                    $isCorrect = $pointsAwarded > 0;
                    $resultsMap[$question->id] = $isCorrect;

                    $mediaAnswer = $this->scoringService->storeAudioFile($request, $attempt->id, $index);

                    $updateData = [
                        'skill_id' => $skillId,
                        'option_id' => $ans['option_id'] ?? null,
                        'text_answer' => $this->scoringService->serializeAnswerForStorage($question, $ans),
                        'is_correct' => $isCorrect,
                        'points_awarded' => $pointsAwarded
                    ];

                    if ($mediaAnswer !== null) {
                        $updateData['media_answer'] = $mediaAnswer;
                    }

                    StudentAnswer::updateOrCreate(
                        [
                            'exam_attempt_id' => $attempt->id,
                            'question_id' => $question->id,
                        ],
                        $updateData
                    );
                }

                // ✅ save last question per skill
                if ($lastQuestionId) {
                    $skillState['last_question_id'] = $lastQuestionId;
                    $skillState['updated_at'] = now()->toDateTimeString();
                    $pos[$skillId] = $skillState;

                    $attempt->update([
                        'current_position' => $pos,
                        'last_seen_question_id' => $lastQuestionId
                    ]);
                }

                $passThreshold = $level->pass_threshold ?? 70;

                $batchScore = $totalPossiblePoints > 0
                    ? round(($earnedPoints / $totalPossiblePoints) * 100, 1)
                    : 100;

                $passed = $batchScore >= $passThreshold;

                $levelScore = $this->attemptService->computeLevelScore($attempt, $skillId, $level);
                $remainingCount = $this->attemptService->countRemainingQuestions($attempt, $skillId, $level);

                $student = $attempt->student;
                $isContinue = $student ? $student->is_continue : false;

                if ($isContinue ? ($remainingCount === 0) : ($remainingCount === 0 || !$passed)) {
                    $this->attemptService->logLevelResult($attempt, $skillId, $level, $levelScore, $passThreshold);
                }

                $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
                $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);

                [$nextPos, $skillEnded, $placementLevel] = $this->resolveProgression(
                    $attempt,
                    $pos,
                    $level,
                    $skillId,
                    $levelNum,
                    $passed,
                    $isContinue,
                    $remainingCount,
                    $this->attemptService->nextLevelExists($attempt->exam_id, $skillId, $levelNum),
                    $skillScore,
                    $student,
                    $request->answers,
                    $resultsMap,
                    $levelScore,
                    $passThreshold
                );

                $finishedExam = false;

                if ($skillEnded) {

                    try {
                        $admins = User::whereIn('role', ['admin', 'teacher'])->get();

                        if ($admins->isNotEmpty()) {
                            $skill = $attempt->exam->skills->firstWhere('id', $skillId);

                            if ($skill) {
                                Notification::send($admins, new SkillCompletedNotification($attempt, $skill));
                            }
                        }
                    } catch (\Exception $e) {
                        $finishedExam = true;
                    }

                    try {
                        $advanced = $this->attemptService
                            ->advanceToNextSkillOrFinish($attempt, $nextPos, $skillId);

                        $nextPos = $advanced['next_pos'];

                        $completedSkills = $nextPos['completed_skills'] ?? [];
                        $allCompleted = count($completedSkills) >= count($pos['skill_ids']);

                        if ($allCompleted || ($advanced['finished_exam'] ?? false)) {
                            $finishedExam = $allCompleted;
                        }

                    } catch (\Exception $e) {
                        \Log::error($e->getMessage());

                        return response()->json([
                            'error' => 'Failed to advance skill'
                        ], 500);
                    }
                }

                $attempt->update([
                    'current_position' => $nextPos
                ]);

                if ($finishedExam) {
                    $this->attemptService->completeAttempt($attempt);
                }

                return response()->json([
                    'passed_level' => $passed,
                    'batch_score' => $batchScore,
                    'skill_ended' => $skillEnded,
                    'finished_exam' => $finishedExam,
                    'placement_level' => $placementLevel,
                    'placement_score' => $skillScore,
                    'is_continue' => $isContinue,
                    'retry_attempt' => (!$passed && !$skillEnded && !$isContinue),
                    'next_step' => $finishedExam ? 'results' : ($skillEnded ? 'dashboard' : 'next_batch'),
                ]);

            });

        } catch (\Throwable $e) {

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }






    public function saveSingleAnswer(Request $request, ExamAttempt $attempt)
    {
        $this->authorize('update', $attempt);
        $request->validate([
            'question_id' => 'required|exists:questions,id',
        ]);

        if ($attempt->status !== 'ongoing') {
            return response()->json(['error' => 'Exam is not active.'], 403);
        }

        $question = Question::with('options')->findOrFail($request->question_id);

        $pointsAwarded = $this->scoringService->gradeAnswer($question, $request->all());
        $isCorrect = $pointsAwarded > 0;

        $mediaAnswer = null;
        if ($request->hasFile('audio_file')) {
            // Speaking: single audio file
            $mediaAnswer = $request->file('audio_file')->store("attempts/{$attempt->id}/answers", 'public');
        } elseif ($request->hasFile('pdf_files')) {
            // Writing: multiple PDF files — store paths as JSON array
            $paths = [];
            foreach ($request->file('pdf_files') as $file) {
                $paths[] = $file->store("attempts/{$attempt->id}/answers", 'public');
            }
            $mediaAnswer = json_encode($paths, JSON_UNESCAPED_SLASHES);
        } elseif ($request->hasFile('pdf_file')) {
            // Legacy / single PDF fallback
            $mediaAnswer = $request->file('pdf_file')->store("attempts/{$attempt->id}/answers", 'public');
        }

        $textAnswer = $this->scoringService->serializeAnswerForStorage($question, $request->all());
        $wordCount = $this->calculateWordCount($textAnswer, $question->type);

        $answer = StudentAnswer::updateOrCreate(
            [
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
            ],
            [
                'skill_id' => $question->skill_id,
                'option_id' => $request->option_id ?? null,
                'text_answer' => $textAnswer,
                'media_answer' => $mediaAnswer,
                'word_count' => $wordCount,
                'is_correct' => $isCorrect,
                'points_awarded' => $pointsAwarded
            ]
        );

        $attempt->update(['last_seen_question_id' => $question->id]);

        return response()->json(['success' => true, 'answer_id' => $answer->id]);
    }

    public function updateProgress(Request $request, ExamAttempt $attempt)
    {
        $this->authorize('update', $attempt);
        $request->validate(['question_id' => 'required|exists:questions,id']);
        if ($attempt->status !== 'ongoing')
            return response()->json(['error' => 'Exam is not active.'], 403);
        $attempt->update(['last_seen_question_id' => $request->question_id]);
        return response()->json(['success' => true]);
    }

    // --- Private Helpers ---

    private function handleEmptyBatch(Request $request, ExamAttempt $attempt, array $pos, int $skillId, int $levelNum, Level $level, int $retryCount)
    {
        $isDemo = $this->examService->isDemoUser($request->user());
        if ($isDemo && $levelNum > 1) {
            StudentAnswer::where('exam_attempt_id', $attempt->id)->whereHas('question', fn($q) => $q->where('skill_id', $skillId))->delete();
            ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->where('skill_id', $skillId)->delete();
            $pos[$skillId]['current_level'] = 1;
            $attempt->update(['current_position' => $pos]);
            return $this->getNextBatch($request, $attempt, $retryCount + 1);
        }
        if ($isDemo && $levelNum === 1) {
            $nextSkillIndex = $pos['current_skill_index'] + 1;
            if ($nextSkillIndex < $attempt->exam->skills()->count()) {
                $pos['current_skill_index'] = $nextSkillIndex;
                $pos[$skillId]['current_level'] = 1;
                $attempt->update(['current_position' => $pos]);
                return $this->getNextBatch($request, $attempt, $retryCount + 1);
            }
        }

        // ─── Resume guard ────────────────────────────────────────────────────
        // After a refresh / logo-tap the student lands back in getNextBatch.
        // If ALL questions at this level are already answered we need to recover
        // the progression (advance level / finalise skill) instead of showing an
        // error.  But ONLY do this when the student has genuinely answered every
        // question — never when they simply haven't answered yet (e.g. a
        // speaking_live question they are still recording).
        $totalQuestionsAtLevel = Question::where('exam_id', $attempt->exam_id)
            ->where('skill_id', $skillId)
            ->where('level_id', $level->id)
            ->count();

        $answeredAtLevel = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereHas('question', fn($q) => $q
                ->where('skill_id', $skillId)
                ->where('level_id', $level->id))
            ->count();

        // Only auto-advance when every question has a saved answer row.
        // If none are answered yet (e.g. student refreshed before submitting),
        // fall through to the is_empty error so the frontend can handle it.
        if ($totalQuestionsAtLevel > 0 && $answeredAtLevel >= $totalQuestionsAtLevel) {

            $levelScore    = $this->attemptService->computeLevelScore($attempt, $skillId, $level);
            $passThreshold = $level->pass_threshold ?? 70;
            $passed        = $levelScore >= $passThreshold;

            // Log level result (idempotent — uses updateOrCreate internally).
            $this->attemptService->logLevelResult($attempt, $skillId, $level, $levelScore, $passThreshold);

            $nextLevelExists = $this->attemptService->nextLevelExists($attempt->exam_id, $skillId, $levelNum);

            if ($nextLevelExists) {
                // Advance to the next level and fetch its questions.
                $pos[(string) $skillId]['current_level'] = $levelNum + 1;
                $attempt->update(['current_position' => $pos]);
                return $this->getNextBatch($request, $attempt, $retryCount + 1);
            }

            // No more levels in this skill → finalise and move on.
            $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
            $maxLevel   = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $skillId)
                ->max('level_number') ?? $levelNum;

            $this->attemptService->finalizeSkill(
                $attempt,
                $skillId,
                $skillScore,
                $maxLevel,
                $passed ? 'completed' : 'failed',
                $maxLevel
            );

            $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);

            $advanced     = $this->attemptService->advanceToNextSkillOrFinish($attempt, $pos, $skillId);
            $nextPos      = $advanced['next_pos'];
            $finishedExam = $advanced['finished_exam'] ?? false;

            $completedSkills = $nextPos['completed_skills'] ?? [];
            if (count($completedSkills) >= count($pos['skill_ids'])) {
                $finishedExam = true;
            }

            $attempt->update(['current_position' => $nextPos]);

            if ($finishedExam) {
                $this->attemptService->completeAttempt($attempt);
                return response()->json([
                    'skill_ended'     => true,
                    'finished_exam'   => true,
                    'next_step'       => 'results',
                    'placement_level' => $maxLevel,
                    'placement_score' => $skillScore,
                ]);
            }

            // More skills remain — transparently fetch the next batch.
            return $this->getNextBatch($request, $attempt, $retryCount + 1);
        }
        // ─── End resume guard ─────────────────────────────────────────────────

        return response()->json(['error' => "Empty Question Set", 'is_empty' => true], 404);
    }

    private function resolveProgression(ExamAttempt $attempt, array $pos, Level $level, int $skillId, int $levelNum, bool $passed, bool $isContinue, int $remainingCount, bool $nextLevelExists, float $skillScore, $student, array $rawAnswers, array $resultsMap, float $levelScore, float $passThreshold): array
    {
        $nextPos = $pos;
        $skillEnded = false;
        $placementLevel = null;

        if ($isContinue) {

            if ($remainingCount === 0) {

                if ($nextLevelExists) {

                    // ✅ يكمل للمستوى التالي بغض النظر عن النجاح أو الرسوب
                    $nextPos[(string) $skillId]['current_level'] = $levelNum + 1;

                } else {

                    // ✅ خلّص آخر مستوى → ينهي المهارة
                    $skillEnded = true;
                    $placementLevel = $levelNum;

                    $this->attemptService->finalizeSkill(
                        $attempt,
                        $skillId,
                        $skillScore,
                        $levelNum,
                        $passed ? 'completed' : 'failed',
                        $placementLevel
                    );
                }
            }

        } else {

            if (!$passed) {

                if ($student?->allows_retry && $level->allows_retry) {

                    // إعادة نفس المستوى
                    $nextPos[(string) $skillId]['current_level'] = $levelNum;

                } else {

                    // ❌ خروج بسبب فشل بدون retry
                    $skillEnded = true;
                    $placementLevel = $levelNum;

                    $this->recordExitQuestion(
                        $attempt,
                        $rawAnswers,
                        $resultsMap
                    );

                    $this->attemptService->finalizeSkill(
                        $attempt,
                        $skillId,
                        $skillScore,
                        $levelNum,
                        'failed',
                        $placementLevel
                    );
                }

            } elseif ($passed) {

                if (
                    $this->attemptService->hasPreviousFailure(
                        $attempt,
                        $skillId,
                        $levelNum
                    )
                ) {

                    // كان فيه فشل سابق → خروج نهائي
                    $skillEnded = true;
                    $placementLevel = $levelNum;

                    $this->attemptService->finalizeSkill(
                        $attempt,
                        $skillId,
                        $skillScore,
                        $levelNum,
                        'failed',
                        $placementLevel
                    );

                } elseif ($remainingCount === 0) {

                    if ($nextLevelExists) {

                        $nextPos[(string) $skillId]['current_level'] = $levelNum + 1;

                    } else {

                        $skillEnded = true;
                        $placementLevel = $levelNum;

                        $this->attemptService->finalizeSkill(
                            $attempt,
                            $skillId,
                            $skillScore,
                            $levelNum,
                            'completed',
                            $placementLevel
                        );
                    }
                }
            }
        }

        return [
            $nextPos,
            $skillEnded,
            $placementLevel
        ];
    }

    private function calculateWordCount(?string $textAnswer, string $questionType): ?int
    {
        // Only calculate word count for writing tasks and text-based answers
        if (!$textAnswer || !in_array($questionType, ['writing', 'short_answer', 'listening'])) {
            return null;
        }

        // Strip HTML tags and trim whitespace
        $cleanText = strip_tags($textAnswer);
        $cleanText = trim($cleanText);

        // If empty after cleaning, return 0
        if (empty($cleanText)) {
            return 0;
        }

        // Split by whitespace and count words
        $words = preg_split('/\s+/', $cleanText, -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }

    private function recordExitQuestion(ExamAttempt $attempt, array $rawAnswers, array $resultsMap): void
    {
        $firstWrongId = null;
        foreach ($rawAnswers as $ans) {
            if (isset($resultsMap[$ans['question_id']]) && !$resultsMap[$ans['question_id']]) {
                $firstWrongId = $ans['question_id'];
                break;
            }
        }
        $lastId = $firstWrongId ?? (count($rawAnswers) > 0 ? end($rawAnswers)['question_id'] : null);
        if ($lastId)
            $attempt->update(['last_seen_question_id' => $lastId]);
    }
}
