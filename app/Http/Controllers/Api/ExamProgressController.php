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
        if ($retryCount > 10) return response()->json(['error' => 'Infinite recursion detected.'], 500);
        if ($attempt->status !== 'ongoing') return response()->json(['error' => 'Exam is not active.'], 403);

        $attempt->loadMissing('exam.skills');
        $pos = $attempt->current_position ?? [];
        if (!isset($pos['skill_ids'][$pos['current_skill_index']])) return response()->json(['error' => 'Skill not found.'], 500);

        $skillId = $pos['skill_ids'][$pos['current_skill_index']];
        $levelNum = $pos['current_level'];

        $level = Level::where('skill_id', $skillId)->where('level_number', $levelNum)->first();
        if (!$level) return response()->json(['error' => "Level missing."], 404);

        $questions = $this->questionService->fetchBatchForLevel($attempt->exam_id, $attempt->id, $skillId, $level);
        $questions = $questions->map(function ($q) { $q->setRelation('options', $q->options->shuffle()); return $q; });

        if ($questions->isEmpty()) return $this->handleEmptyBatch($request, $attempt, $pos, $skillId, $levelNum, $level, $retryCount);

        $skillRecord = ExamAttemptSkill::firstOrCreate(['exam_attempt_id' => $attempt->id, 'skill_id' => $skillId], ['started_at' => now()]);
        if (empty($pos['current_skill_started_at']) || $pos['current_skill_started_at'] !== $skillRecord->started_at->toIso8601String()) {
            $pos['current_skill_started_at'] = $skillRecord->started_at->toIso8601String();
            $attempt->update(['current_position' => $pos]);
        }

        $skillDuration = DB::table('exam_skill')->where('exam_id', $attempt->exam_id)->where('skill_id', $skillId)->value('duration') ?? 0;
        $isDemo = $this->examService->isDemoUser($request->user());

        return response()->json([
            'skill' => $attempt->exam->skills->firstWhere('id', $skillId),
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

    public function submitBatch(Request $request, ExamAttempt $attempt)
    {
        $this->authorize('update', $attempt);
        $request->validate(['answers' => 'required|array', 'answers.*.question_id' => 'required|exists:questions,id']);

        return DB::transaction(function () use ($request, $attempt) {
            // Lock the attempt for update to prevent race conditions
            $attempt = ExamAttempt::where('id', $attempt->id)->with('exam.skills')->lockForUpdate()->first();

            if ($attempt->status !== 'ongoing') {
                return response()->json(['error' => 'Exam is not active.'], 403);
            }

            $pos = $attempt->current_position ?? [];
            $skillId = $pos['skill_ids'][$pos['current_skill_index']] ?? null;
            $levelNum = $pos['current_level'] ?? null;

            if (!$skillId || !$levelNum) {
                return response()->json(['error' => 'Invalid exam position.'], 500);
            }

            $level = Level::where('skill_id', $skillId)->where('level_number', $levelNum)->first();
            if (!$level) return response()->json(['error' => "Level not found."], 404);

            $questionIds = collect($request->answers)->pluck('question_id')->unique()->toArray();

            // Prevention: Check if any of these questions have already been answered in this attempt
            $alreadyAnswered = StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->whereIn('question_id', $questionIds)
                ->exists();

            if ($alreadyAnswered) {
                return response()->json(['error' => 'This batch has already been submitted.'], 422);
            }

            $questionsMap = Question::with('options')->whereIn('id', $questionIds)->get()->keyBy('id');

            $earnedPoints = 0; $totalPossiblePoints = 0; $resultsMap = [];
            foreach ($request->answers as $index => $ans) {
                $question = $questionsMap->get($ans['question_id']);
                if (!$question) continue;
                $totalPossiblePoints += $question->points;
                $pointsAwarded = $this->scoringService->gradeAnswer($question, $ans);
                $isCorrect = $pointsAwarded > 0;
                $resultsMap[$question->id] = $isCorrect;
                $earnedPoints += $pointsAwarded;

                StudentAnswer::create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'skill_id' => $skillId,
                    'option_id' => $ans['option_id'] ?? null,
                    'text_answer' => $this->scoringService->serializeAnswerForStorage($question, $ans),
                    'media_answer' => $this->scoringService->storeAudioFile($request, $attempt->id, $index),
                    'is_correct' => $isCorrect,
                    'points_awarded' => $pointsAwarded
                ]);
            }

            $passThreshold = $level->pass_threshold ?? 70;
            $batchScore = $totalPossiblePoints > 0 ? round(($earnedPoints / $totalPossiblePoints) * 100, 1) : 0;
            $passed = $batchScore >= $passThreshold;

            $levelScore = $this->attemptService->computeLevelScore($attempt, $skillId, $level);
            $remainingCount = $this->attemptService->countRemainingQuestions($attempt, $skillId, $level);
            $student = $attempt->student;
            $isAdaptive = $student ? !$student->not_adaptive : true;

            if ($isAdaptive ? ($remainingCount === 0) : ($remainingCount === 0 || !$passed)) {
                $this->attemptService->logLevelResult($attempt, $skillId, $level, $levelScore, $passThreshold);
            }

            $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
            $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);

            [$nextPos, $skillEnded, $placementLevel] = $this->resolveProgression($attempt, $pos, $level, $skillId, $levelNum, $passed, $isAdaptive, $remainingCount, $this->attemptService->nextLevelExists($attempt->exam_id, $skillId, $levelNum), $skillScore, $student, $request->answers, $resultsMap);

            $finishedExam = false;
            if ($skillEnded) {
                Notification::send(User::whereIn('role', ['admin', 'teacher'])->get(), new \App\Notifications\SkillCompletedNotification($attempt, $attempt->exam->skills->firstWhere('id', $skillId)));
                $advanced = $this->attemptService->advanceToNextSkillOrFinish($attempt, $nextPos, $skillId);
                $nextPos = $advanced['next_pos'];
                $allCompleted = true;
                foreach ($pos['skill_ids'] as $id) { if (!in_array($id, $nextPos['completed_skills'])) { $allCompleted = false; break; } }
                if ($allCompleted || $advanced['finished_exam']) $finishedExam = $allCompleted;
            }

            $attempt->update(['current_position' => $nextPos]);
            if ($finishedExam) $this->attemptService->completeAttempt($attempt);

            return response()->json([
                'passed_level' => $passed, 'batch_score' => $batchScore, 'skill_ended' => $skillEnded, 'finished_exam' => $finishedExam,
                'placement_level' => $placementLevel, 'placement_score' => $skillScore, 'is_adaptive' => $isAdaptive,
                'retry_attempt' => (!$passed && !$skillEnded && !$isAdaptive), 'next_step' => $finishedExam ? 'results' : ($skillEnded ? 'dashboard' : 'next_batch'),
            ]);
        });
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
            $pos['current_level'] = 1; $attempt->update(['current_position' => $pos]);
            return $this->getNextBatch($request, $attempt, $retryCount + 1);
        }
        if ($isDemo && $levelNum === 1) {
            $nextSkillIndex = $pos['current_skill_index'] + 1;
            if ($nextSkillIndex < $attempt->exam->skills()->count()) {
                $pos['current_skill_index'] = $nextSkillIndex; $pos['current_level'] = 1; $attempt->update(['current_position' => $pos]);
                return $this->getNextBatch($request, $attempt, $retryCount + 1);
            }
        }
        return response()->json(['error' => "Empty Question Set", 'is_empty' => true], 404);
    }

    private function resolveProgression(ExamAttempt $attempt, array $pos, Level $level, int $skillId, int $levelNum, bool $passed, bool $isAdaptive, int $remainingCount, bool $nextLevelExists, float $skillScore, $student, array $rawAnswers, array $resultsMap): array
    {
        $nextPos = $pos; $skillEnded = false; $placementLevel = null;
        if ($isAdaptive) {
            if ($remainingCount === 0) {
                if ($nextLevelExists) $nextPos['current_level'] = $levelNum + 1;
                else { $skillEnded = true; $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $levelNum, 'completed'); }
            } else $nextPos['current_level'] = $levelNum;
        } else {
            if (!$passed) {
                if ($student?->allows_retry && $level->allows_retry) $nextPos['current_level'] = $levelNum;
                else { $skillEnded = true; $placementLevel = $levelNum; $this->recordExitQuestion($attempt, $rawAnswers, $resultsMap); $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $levelNum, 'failed', max($levelNum - 1, 1)); }
            } elseif ($passed) {
                if ($this->attemptService->hasPreviousFailure($attempt, $skillId, $levelNum)) { $skillEnded = true; $placementLevel = $levelNum; $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $levelNum, 'completed', $levelNum); }
                elseif ($remainingCount === 0) {
                    if ($nextLevelExists) $nextPos['current_level'] = $levelNum + 1;
                    else { $skillEnded = true; $placementLevel = $levelNum; $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $levelNum, 'completed', $levelNum); }
                }
            }
        }
        return [$nextPos, $skillEnded, $placementLevel];
    }

    private function recordExitQuestion(ExamAttempt $attempt, array $rawAnswers, array $resultsMap): void
    {
        $firstWrongId = null;
        foreach ($rawAnswers as $ans) { if (isset($resultsMap[$ans['question_id']]) && !$resultsMap[$ans['question_id']]) { $firstWrongId = $ans['question_id']; break; } }
        $lastId = $firstWrongId ?? (count($rawAnswers) > 0 ? end($rawAnswers)['question_id'] : null);
        if ($lastId) $attempt->update(['last_seen_question_id' => $lastId]);
    }
}
