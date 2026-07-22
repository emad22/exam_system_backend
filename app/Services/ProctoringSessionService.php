<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ProctoringSession;
use Illuminate\Support\Str;

class ProctoringSessionService
{



    public function initiate(?ExamAttempt $attempt, $request, ?int $sessionId = null): ProctoringSession
    {
        $studentId = auth()->user()->student?->id;
        $user = auth()->user();
        $isDemo = app(\App\Services\ExamService::class)->isDemoUser($user);
        $tokenId = $user->currentAccessToken() ? $user->currentAccessToken()->id : null;

        if ($attempt) {
            abort_if($attempt->student_id !== $studentId && $attempt->user_id !== $user->id, 403);
        }

        // 1. لو المحاولة منتهية، مفيش جلسة جديدة
        if ($attempt && $attempt->status === 'completed') {
            return new ProctoringSession([
                'status' => 'ended',
                'exam_attempt_id' => $attempt->id,
            ]);
        }

        // 2. إذا تم تمرير sessionId وكانت الجلسة صالحة وغير منتهية، نستخدمها ونربطها بالمحاولة
        if ($sessionId) {
            $sessionQuery = ProctoringSession::where('student_id', $studentId)
                ->where('id', $sessionId);

            if ($isDemo && $tokenId) {
                $sessionQuery->where('sanctum_token_id', $tokenId);
            }

            $session = $sessionQuery->first();
            if ($session && $session->status !== 'ended') {
                // التأكد من تطابق محاولة الامتحان لمنع استخدام جلسة تخص محاولة أخرى
                if ($attempt && $session->exam_attempt_id && $session->exam_attempt_id !== $attempt->id) {
                    $session = null;
                }
            } else {
                $session = null;
            }

            if ($session) {
                if ($attempt && !$session->exam_attempt_id) {
                    $session->update([
                        'exam_attempt_id' => $attempt->id,
                        'status' => 'active',
                    ]);
                }
                return $session;
            }
        }

        // 3. لو فيه جلسة مربوطة بنفس المحاولة دي بالظبط ولسه شغالة، استخدمها
        if ($attempt) {
            $sessionQuery = ProctoringSession::where('student_id', $studentId)
                ->where('exam_attempt_id', $attempt->id)
                ->whereIn('status', ['pending', 'active', 'paused']);

            if ($isDemo && $tokenId) {
                $sessionQuery->where('sanctum_token_id', $tokenId);
            }

            $existingForAttempt = $sessionQuery->latest()->first();

            if ($existingForAttempt) {
                return $existingForAttempt;
            }
        }

        // 4. لو فيه جلسة "عامة" اتعملت وقت اللوجين (من غير exam_attempt_id) ولسه شغالة،
        //    نلحق المحاولة الحالية بيها ونخليها active بدل ما ننشئ صف جديد
        $sessionQuery = ProctoringSession::where('student_id', $studentId)
            ->whereNull('exam_attempt_id')
            ->whereIn('status', ['pending', 'active', 'paused']);

        if ($isDemo && $tokenId) {
            $sessionQuery->where('sanctum_token_id', $tokenId);
        }

        $sessionLevel = $sessionQuery->latest()->first();

        if ($sessionLevel) {
            if ($attempt) {
                $sessionLevel->update([
                    'exam_attempt_id' => $attempt->id,
                    'status' => 'active',
                ]);
            }
            return $sessionLevel->refresh();
        }

        // 5. مفيش أي جلسة موجودة → ننشئ جلسة جديدة
        return ProctoringSession::create([
            'student_id' => $studentId,
            'exam_attempt_id' => $attempt?->id,
            'status' => 'pending',
            'session_token' => Str::random(64),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'risk_score' => 0,
            'violations_count' => 0,
            'sanctum_token_id' => $isDemo ? $tokenId : null
        ]);
    }




    /**
     * Start session (idempotent)
     */
    public function start(ProctoringSession $session): array
    {
        $this->authorize($session);

        if ($session->status === 'active') {
            return $this->response($session, 'already_active');
        }

        abort_if($session->status !== 'pending' && $session->status !== 'active', 422, 'Invalid state');

        $session->update([
            'status' => 'active',
            'recording_status' => 'recording',
            'started_at' => $session->started_at ?? now(),
            'resumed_at' => null,
        ]);

        return $this->response($session, 'started');
    }

    /**
     * Pause session
     */
    public function pause(ProctoringSession $session): array
    {
        $this->authorize($session);

        abort_if($session->status !== 'active', 422, 'Session not active');

        $session->update([
            'status' => 'paused',
            'recording_status' => 'paused',
            'paused_at' => now(),
        ]);

        return $this->response($session, 'paused');
    }

    /**
     * Resume session — accumulate how long we were paused
     */
    public function resume(ProctoringSession $session): array
    {
        $this->authorize($session);

        abort_if($session->status !== 'paused', 422, 'Session not paused');

        // Accumulate the duration of this pause period
        $additionalPaused = 0;
        if ($session->paused_at) {
            $additionalPaused = abs(now()->diffInSeconds($session->paused_at));
        }
        $currentTotalPaused = (int) ($session->total_paused_seconds ?? 0);

        $session->update([
            'status' => 'active',
            'recording_status' => 'recording',
            'resumed_at' => now(),
            'paused_at' => null,
            'total_paused_seconds' => $currentTotalPaused + $additionalPaused,
        ]);

        return $this->response($session, 'resumed');
    }

    /**
     * End session (final state)
     */
    public function end(ProctoringSession $session, string $reason): array
    {
        $this->authorize($session);

        abort_if($session->status === 'ended', 422, 'Already ended');

        $duration = $this->calculateDuration($session);

        $session->update([
            'status' => 'ended',
            'recording_status' => 'completed',
            'ended_at' => now(),
            'closed_at' => now(),
            'close_reason' => $reason,
            'duration_seconds' => $duration,
        ]);

        return $this->response($session, 'ended');
    }

    /**
     * Duration with cumulative pause handling
     */
    private function calculateDuration(ProctoringSession $session): int
    {
        if (!$session->started_at) {
            return 0;
        }

        $totalSeconds = abs(now()->diffInSeconds($session->started_at));

        // Total accumulated pause time from all previous pause-resume cycles
        $storedPausedSeconds = (int) ($session->total_paused_seconds ?? 0);

        // Add the current (ongoing) pause period if session is still paused
        $currentPausePeriod = 0;
        if ($session->status === 'paused' && $session->paused_at) {
            $currentPausePeriod = abs(now()->diffInSeconds($session->paused_at));
        }

        $totalPaused = $storedPausedSeconds + $currentPausePeriod;

        return max(0, $totalSeconds - $totalPaused);
    }

    /**
     * Security check
     */
    private function authorize(ProctoringSession $session): void
    {
        $studentId = auth()->user()->student?->id;

        abort_if(
            $session->student_id !== $studentId,
            403,
            'Unauthorized session access'
        );
    }

    /**
     * Standard response format
     */
    private function response(ProctoringSession $session, string $action): array
    {
        return [
            'success' => true,
            'action' => $action,
            'session_id' => $session->id,
            'status' => $session->status,
            'risk_score' => $session->risk_score,
        ];
    }




    public function recordSkillEntry(ProctoringSession $session, int $skillId): void
    {
        $session->skills()->syncWithoutDetaching([
            $skillId => ['entered_at' => now()]
        ]);
    }

    public function recordSkillExit(ProctoringSession $session, int $skillId, int $questionsAnswered = 0): void
    {
        $session->skills()->updateExistingPivot($skillId, [
            'exited_at' => now(),
            'questions_answered' => $questionsAnswered,
        ]);
    }
}