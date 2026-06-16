<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    ) {}

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
    $levelNum = $pos[$skillId]['current_level'] ?? 1;

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
    if (empty($pos['current_skill_started_at']) ||
        $pos['current_skill_started_at'] !== $skillRecord->started_at->toIso8601String()) {

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

    return response()->json([
        'skill' => $attempt->exam->skills->firstWhere('id', $skillId),
        'level' => $level,
        'questions' => $questions,
        'total_questions' => $this->questionService
            ->getTotalLevelQuestions($attempt->exam_id, $skillId, $level->id),

        'timer_type' => $attempt->exam->timer_type ?? 'global',
        'time_limit' => $attempt->exam->time_limit ?? 0,
        'skill_duration' => $isDemo ? 0 : $skillDuration,

        'current_skill_started_at' => $pos['current_skill_started_at'],
        'skill_cheat_warnings' => $skillRecord->cheat_warnings ?? 0,

        // مهم جداً للتتبع
        'current_level' => $levelNum,
    ]);
}

    // public function submitBatch(Request $request, ExamAttempt $attempt)
    // {
    //     $this->authorize('update', $attempt);
    //     // question_id is always required; other fields depend on question type (speaking uses audio_file)
    //     $request->validate([
    //         'answers'                  => 'required|array',
    //         'answers.*.question_id'    => 'required|exists:questions,id',
    //     ]);

    //     try {
    //         return DB::transaction(function () use ($request, $attempt) {
    //         // Lock the attempt for update to prevent race conditions
    //         $attempt = ExamAttempt::where('id', $attempt->id)->with('exam.skills')->lockForUpdate()->first();

    //         if ($attempt->status !== 'ongoing') {
    //             return response()->json(['error' => 'Exam is not active.'], 403);
    //         }

    //         $pos = $attempt->current_position ?? [];
    //         $skillId = $pos['skill_ids'][$pos['current_skill_index']] ?? null;
    //         $levelNum = $pos['current_level'] ?? null;

    //         if (!$skillId || !$levelNum) {
    //             return response()->json(['error' => 'Invalid exam position.'], 500);
    //         }

    //         $level = Level::where('skill_id', $skillId)->where('level_number', $levelNum)->first();
    //         if (!$level) return response()->json(['error' => "Level not found."], 404);

    //         $questionIds = collect($request->answers)->pluck('question_id')->unique()->toArray();

    //         // Prevention: Check if any of these questions have already been answered in this attempt
    //         // Exception: If we are doing partial saves, we might want to allow re-submission
    //         // But for the final batch submit, we expect them to be "fresh" or we just update them.
            
    //         $questionsMap = Question::with('options')->whereIn('id', $questionIds)->get()->keyBy('id');

    //         $earnedPoints = 0; $totalPossiblePoints = 0; $resultsMap = [];
    //         foreach ($request->answers as $index => $ans) {
    //             $question = $questionsMap->get($ans['question_id']);
    //             if (!$question) continue;
                
    //             // Speaking and Writing are manually/AI graded. Do not include in automated total possible points.
    //             if (in_array($question->type, ['speaking', 'writing'])) {
    //                 $pointsAwarded = 0; // Default to 0 until manually graded
    //             } else {
    //                 $totalPossiblePoints += $question->points;
    //                 $pointsAwarded = $this->scoringService->gradeAnswer($question, $ans);
    //                 $earnedPoints += $pointsAwarded;
    //             }
                
    //             $isCorrect = $pointsAwarded > 0;
    //             $resultsMap[$question->id] = $isCorrect;

    //             $mediaAnswer = $this->scoringService->storeAudioFile($request, $attempt->id, $index);
    //             $updateData = [
    //                 'skill_id' => $skillId,
    //                 'option_id' => $ans['option_id'] ?? null,
    //                 'text_answer' => $this->scoringService->serializeAnswerForStorage($question, $ans),
    //                 'is_correct' => $isCorrect,
    //                 'points_awarded' => $pointsAwarded
    //             ];
    //             if ($mediaAnswer !== null) {
    //                 $updateData['media_answer'] = $mediaAnswer;
    //             }

    //             StudentAnswer::updateOrCreate(
    //                 [
    //                     'exam_attempt_id' => $attempt->id,
    //                     'question_id' => $question->id,
    //                 ],
    //                 $updateData
    //             );
    //         }

    //         $passThreshold = $level->pass_threshold ?? 70;
    //         // If all questions in the batch were manual grading (speaking/writing), assume 100% so they pass to the next stage/pending review.
    //         $batchScore = $totalPossiblePoints > 0 ? round(($earnedPoints / $totalPossiblePoints) * 100, 1) : 100;
    //         $passed = $batchScore >= $passThreshold;

    //         $levelScore = $this->attemptService->computeLevelScore($attempt, $skillId, $level);
    //         $remainingCount = $this->attemptService->countRemainingQuestions($attempt, $skillId, $level);
    //         $student = $attempt->student;
    //         $isContinue = $student ? $student->is_continue : true;

    //         if ($isContinue ? ($remainingCount === 0) : ($remainingCount === 0 || !$passed)) {
    //             $this->attemptService->logLevelResult($attempt, $skillId, $level, $levelScore, $passThreshold);
    //         }

    //         $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
    //         $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);

    //         [$nextPos, $skillEnded, $placementLevel] = $this->resolveProgression($attempt, $pos, $level, $skillId, $levelNum, $passed, $isContinue, $remainingCount, $this->attemptService->nextLevelExists($attempt->exam_id, $skillId, $levelNum), $skillScore, $student, $request->answers, $resultsMap);
            
    //         $finishedExam = false;
    //         if ($skillEnded) {
    //             // ✅ 1. Protect notification from crashing the request
    //             try {
    //                 $admins = User::whereIn('role', ['admin', 'teacher'])->get();
    //                 if ($admins->isNotEmpty()) {
    //                     $skill = $attempt->exam->skills->firstWhere('id', $skillId);
    //                     if ($skill) {
    //                         Notification::send($admins, new SkillCompletedNotification($attempt, $skill));
    //                     }
    //                 }
    //             } catch (\Exception $e) {
    //                $finishedExam = true;
    //             }

    //             // ✅ 2. Protect advanceToNextSkillOrFinish
    //             try {
    //                 $advanced = $this->attemptService->advanceToNextSkillOrFinish($attempt, $nextPos, $skillId);
    //                 $nextPos = $advanced['next_pos'];
                    
    //                 $completedSkills = $nextPos['completed_skills'] ?? [];
    //                 $allCompleted = count($completedSkills) >= count($pos['skill_ids']);
                    
    //                 if ($allCompleted || ($advanced['finished_exam'] ?? false)) {
    //                     $finishedExam = $allCompleted;
    //                 }
    //             } catch (\Exception $e) {
    //                 \Log::error("advanceToNextSkillOrFinish failed: " . $e->getMessage());
    //                 return response()->json(['error' => 'Failed to advance skill: ' . $e->getMessage()], 500);
    //             }
    //         }

    //         $attempt->update(['current_position' => $nextPos]);
    //         if ($finishedExam) $this->attemptService->completeAttempt($attempt);

    //         return response()->json([
    //             'passed_level'    => $passed,
    //             'batch_score'     => $batchScore,
    //             'skill_ended'     => $skillEnded,
    //             'finished_exam'   => $finishedExam,
    //             'placement_level' => $placementLevel,
    //             'placement_score' => $skillScore,
    //             'is_continue'     => $isContinue,
    //             'retry_attempt'   => (!$passed && !$skillEnded && !$isContinue),
    //             'next_step'       => $finishedExam ? 'results' : ($skillEnded ? 'dashboard' : 'next_batch'),
    //         ]);
    //     });

    //     } catch (\Throwable $e) {

    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //             'file' => $e->getFile(),
    //             'trace' => collect($e->getTrace())->take(10),
    //         ], 500);

    //     }
    // }


public function submitBatch(Request $request, ExamAttempt $attempt)
{
    $this->authorize('update', $attempt);

    $request->validate([
        'answers' => 'required|array',
        'answers.*.question_id' => 'required|exists:questions,id',
    ]);

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

                if (!$question) continue;

                $lastQuestionId = $question->id;

                if (in_array($question->type, ['speaking', 'writing'])) {
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
            $isContinue = $student ? $student->is_continue : true;

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
            $mediaAnswer = $request->file('audio_file')->store("attempts/{$attempt->id}/answers", 'public');
        }

        $textAnswer = $this->scoringService->serializeAnswerForStorage($question, $request->all());
        $wordCount = $this->calculateWordCount($textAnswer, $question->type);

        $answer = StudentAnswer::updateOrCreate(
            [
                'exam_attempt_id' => $attempt->id,
                'question_id'     => $question->id,
            ],
            [
                'skill_id'        => $question->skill_id,
                'option_id'       => $request->option_id ?? null,
                'text_answer'     => $textAnswer,
                'media_answer'    => $mediaAnswer,
                'word_count'      => $wordCount,
                'is_correct'      => $isCorrect,
                'points_awarded'  => $pointsAwarded
            ]
        );

        $attempt->update(['last_seen_question_id' => $question->id]);

        return response()->json(['success' => true, 'answer_id' => $answer->id]);
    }

    public function updateProgress(Request $request, ExamAttempt $attempt)
    {
        $this->authorize('update', $attempt);
        $request->validate(['question_id' => 'required|exists:questions,id']);
        if ($attempt->status !== 'ongoing') return response()->json(['error' => 'Exam is not active.'], 403);
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
        //  return response()->json(['level' => $pos['current_level']] , 200);
        return response()->json(['error' => "Empty Question Set", 'is_empty' => true], 404);
    }

private function resolveProgression( ExamAttempt $attempt, array $pos, Level $level, int $skillId,  int $levelNum,  bool $passed,  bool $isContinue, int $remainingCount, bool $nextLevelExists, float $skillScore,  $student,array $rawAnswers,array $resultsMap,float $levelScore,float $passThreshold): array
{
    $nextPos = $pos;
    $skillEnded = false;
    $placementLevel = null;

    if ($isContinue) {

        if ($remainingCount === 0) {

            /**
             * ❗ أهم شرط عندك:
             * لو الطالب خلّص المستوى
             * بنقرر هنا يكمل أو يخرج بناءً على الدرجة
             */

            if ($levelScore < $passThreshold) {

                // ❌ راسب في المستوى → خروج من المهارة
                $skillEnded = true;

                $placementLevel = max(1, $levelNum - 1);

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

            } elseif ($nextLevelExists) {

                // ✅ نجح → مستوى أعلى
                $nextPos[$skillId]['current_level'] = $levelNum + 1;

            } else {

                // ✅ آخر مستوى ونجح
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

    } else {

        if (!$passed) {

            if ($student?->allows_retry && $level->allows_retry) {

                // إعادة نفس المستوى
                $nextPos[$skillId]['current_level'] = $levelNum;

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

            if ($this->attemptService->hasPreviousFailure(
                $attempt,
                $skillId,
                $levelNum
            )) {

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

                    $nextPos[$skillId]['current_level'] = $levelNum + 1;

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
                 $firstWrongId = $ans['question_id']; break; 
            } 
        }
        $lastId = $firstWrongId ?? (count($rawAnswers) > 0 ? end($rawAnswers)['question_id'] : null);
        if ($lastId) $attempt->update(['last_seen_question_id' => $lastId]);
    }
}
