<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ProctoringSession;
use Illuminate\Support\Str;

class ProctoringSessionService
{
    /**
     * Create or reuse session
     */
    // public function initiate(?ExamAttempt $attempt, $request, ?int $sessionId = null): ProctoringSession
    // {
    //     $studentId = auth()->user()->student?->id;

    //     if ($attempt) {
    //         abort_if($attempt->student_id !== $studentId, 403);
    //     }

    //     // 1. إذا كانت هناك جلسة منتهية مع نفس الطالب، نُنشئ جلسة جديدة مباشرةً.
    //     if ($studentId) {
    //         $latestEndedSession = ProctoringSession::where('student_id', $studentId)
    //             ->where('status', 'ended')
    //             ->latest()
    //             ->first();

    //         if ($latestEndedSession) {
    //             $latestEndedSession->update([
    //                 'status' => 'ended',
    //             ]);
    //         }
    //     }

    //     // 2. إذا كانت المحاولة منتهية، لا نُنشئ جلسة جديدة.
    //     if ($attempt && $attempt->status === 'completed') {
    //         return new ProctoringSession([
    //             'status' => 'ended',
    //             'exam_attempt_id' => $attempt->id,
    //         ]);
    //     }

    //     // 3. إذا كانت هناك جلسة نشطة/معلقة، نعيد استخدامها.
    //     if ($attempt) {
    //         $existingActive = ProctoringSession::where('student_id', $studentId)
    //             ->where('exam_attempt_id', $attempt->id)
    //             ->whereIn('status', ['pending', 'active', 'paused'])
    //             ->latest()
    //             ->first();

    //         if ($existingActive) {
    //             return $existingActive;
    //         }
    //     }

    //     // 4. نُنشئ جلسة جديدة دائمًا عند بدء أي محاولة جديدة.
    //     $session = ProctoringSession::create(
    //         [
    //             'student_id' => $studentId,
    //             'exam_attempt_id' => $attempt?->id,
    //             'status' => 'pending',
    //             'session_token' => Str::random(64),
    //             'ip_address' => $request->ip(),
    //             'user_agent' => $request->userAgent(),
    //             'risk_score' => 0,
    //             'violations_count' => 0,
    //         ]
    //     );

    //     return $session;
    // }



    public function initiate(?ExamAttempt $attempt, $request, ?int $sessionId = null): ProctoringSession
    {
        $studentId = auth()->user()->student?->id;

        if ($attempt) {
            abort_if($attempt->student_id !== $studentId, 403);
        }

        // 1. لو المحاولة منتهية، مفيش جلسة جديدة
        if ($attempt && $attempt->status === 'completed') {
            return new ProctoringSession([
                'status' => 'ended',
                'exam_attempt_id' => $attempt->id,
            ]);
        }

        // 2. لو فيه جلسة مربوطة بنفس المحاولة دي بالظبط ولسه شغالة، استخدمها
        if ($attempt) {
            $existingForAttempt = ProctoringSession::where('student_id', $studentId)
                ->where('exam_attempt_id', $attempt->id)
                ->whereIn('status', ['pending', 'active', 'paused'])
                ->latest()
                ->first();

            if ($existingForAttempt) {
                return $existingForAttempt;
            }
        }

        // 3. لو فيه جلسة "عامة" اتعملت وقت اللوجين (من غير exam_attempt_id) ولسه شغالة،
        //    نلحق المحاولة الحالية بيها بدل ما ننشئ صف جديد
        $sessionLevel = ProctoringSession::where('student_id', $studentId)
            ->whereNull('exam_attempt_id')
            ->whereIn('status', ['pending', 'active', 'paused'])
            ->latest()
            ->first();

        if ($sessionLevel) {
            if ($attempt) {
                $sessionLevel->update([
                    'exam_attempt_id' => $attempt->id,
                ]);
            }
            return $sessionLevel->refresh();
        }

        // 4. مفيش أي جلسة موجودة → ننشئ جلسة جديدة
        return ProctoringSession::create([
            'student_id' => $studentId,
            'exam_attempt_id' => $attempt?->id,
            'status' => 'pending',
            'session_token' => Str::random(64),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'risk_score' => 0,
            'violations_count' => 0,
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