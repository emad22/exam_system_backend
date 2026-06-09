<?php

namespace App\Http\Controllers\Api\Proctoring;

use App\Models\ProctoringSession;
use App\Models\ExamViolation;
use App\Models\CheatingAlert;
use App\Models\ExamAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProctoringController extends Controller
{
    /**
     * بدء جلسة مراقبة جديدة
     */
    public function initiateSession(Request $request)
    {
        $validated = $request->validate([
            'exam_attempt_id' => 'required|exists:exam_attempts,id',
            'student_id' => 'required|exists:users,id',
        ]);

        try {
            $examAttempt = ExamAttempt::findOrFail($validated['exam_attempt_id']);

            // إنشاء جلسة مراقبة جديدة
            $session = ProctoringSession::create([
                'exam_attempt_id' => $validated['exam_attempt_id'],
                'student_id' => $validated['student_id'],
                'status' => 'pending',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_info' => [
                    'platform' => $request->header('User-Agent'),
                    'timestamp' => now(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'session_id' => $session->id,
                'session_token' => Str::random(64),
                'message' => 'Proctoring session initiated successfully',
                'exam_duration' => $examAttempt->exam->duration_minutes,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Failed to initiate proctoring session', [
                'error' => $e->getMessage(),
                'exam_attempt_id' => $validated['exam_attempt_id'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate proctoring session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * الحصول على تفاصيل جلسة المراقبة
     */
    public function getSession($sessionId)
    {
        try {
            $session = ProctoringSession::with([
                'student',
                'examAttempt.exam',
                'violations' => fn($q) => $q->latest()->limit(10),
            ])->findOrFail($sessionId);

            return response()->json([
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'status' => $session->status,
                    'risk_score' => $session->risk_score,
                    'violations_count' => $session->violations_count,
                    'student_name' => $session->student->name,
                    'started_at' => $session->started_at,
                    'violations' => $session->violations->map(fn($v) => [
                        'type' => $v->violation_type,
                        'severity' => $v->severity,
                        'timestamp' => $v->timestamp,
                    ]),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }
    }

    /**
     * بدء التسجيل
     */
    public function startRecording($sessionId)
    {
        try {
            $session = ProctoringSession::findOrFail($sessionId);

            if ($session->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid session status',
                ], 422);
            }

            $session->update([
                'status' => 'active',
                'recording_status' => 'recording',
                'started_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recording started',
                'session_id' => $session->id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start recording',
            ], 500);
        }
    }

    /**
     * إيقاف التسجيل مؤقتاً
     */
    public function pauseRecording($sessionId)
    {
        try {
            $session = ProctoringSession::findOrFail($sessionId);

            if ($session->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Session is not active',
                ], 422);
            }

            $session->update([
                'status' => 'paused',
                'recording_status' => 'paused',
                'paused_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recording paused',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to pause recording',
            ], 500);
        }
    }

    /**
     * استئناف التسجيل
     */
    public function resumeRecording($sessionId)
    {
        try {
            $session = ProctoringSession::findOrFail($sessionId);

            if ($session->status !== 'paused') {
                return response()->json([
                    'success' => false,
                    'message' => 'Session is not paused',
                ], 422);
            }

            $session->update([
                'status' => 'active',
                'recording_status' => 'recording',
                'resumed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recording resumed',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resume recording',
            ], 500);
        }
    }

    /**
     * إنهاء الجلسة
     */
    public function endSession(Request $request, $sessionId)
    {
        $validated = $request->validate([
            'end_reason' => 'required|in:exam_submitted,time_ended,terminated_by_proctor,connection_lost',
        ]);

        try {
            $session = ProctoringSession::findOrFail($sessionId);

            $session->update([
                'status' => 'ended',
                'recording_status' => 'completed',
                'ended_at' => now(),
                'duration_seconds' => $session->started_at 
                    ? now()->diffInSeconds($session->started_at)
                    : 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session ended successfully',
                'duration' => $session->duration_seconds,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to end session',
            ], 500);
        }
    }

    /**
     * تسجيل انتهاك
     */
    public function reportViolation(Request $request, $sessionId)
    {
        $validated = $request->validate([
            'violation_type' => 'required|in:multiple_faces,face_not_visible,face_swap,tab_switched,browser_opened,copy_paste,external_device,suspicious_audio,suspicious_behavior,environment_change,person_in_background,phone_usage,unusual_eye_movement',
            'severity' => 'required|in:info,low,medium,high,critical',
            'description' => 'nullable|string',
            'evidence' => 'nullable|array',
        ]);

        try {
            $session = ProctoringSession::findOrFail($sessionId);

            // إنشاء سجل الانتهاك
            $violation = ExamViolation::create([
                'proctoring_session_id' => $session->id,
                'student_id' => $session->student_id,
                'violation_type' => $validated['violation_type'],
                'severity' => $validated['severity'],
                'description' => $validated['description'],
                'evidence' => $validated['evidence'] ?? [],
                'detected_by' => 'system',
                'timestamp' => now(),
            ]);

            // تحديث إحصائيات الجلسة
            $session->increment('violations_count');

            // إنشاء تنبيه
            CheatingAlert::create([
                'proctoring_session_id' => $session->id,
                'violation_id' => $violation->id,
                'alert_type' => 'instant',
                'message' => "تم كشف انتهاك: {$validated['violation_type']}",
                'severity' => $validated['severity'],
            ]);

            // حساب risk score
            $this->updateRiskScore($session);

            return response()->json([
                'success' => true,
                'violation_id' => $violation->id,
                'message' => 'Violation recorded',
                'new_risk_score' => $session->risk_score,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to report violation', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to report violation',
            ], 500);
        }
    }

    /**
     * الحصول على جميع الانتهاكات
     */
    public function getViolations($sessionId)
    {
        try {
            $session = ProctoringSession::findOrFail($sessionId);
            $violations = $session->violations()->latest()->get();

            return response()->json([
                'success' => true,
                'violations_count' => count($violations),
                'violations' => $violations->map(fn($v) => [
                    'id' => $v->id,
                    'type' => $v->violation_type,
                    'severity' => $v->severity,
                    'description' => $v->description,
                    'timestamp' => $v->timestamp,
                ]),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch violations',
            ], 500);
        }
    }

    /**
     * حساب risk score
     */
    private function updateRiskScore(ProctoringSession $session)
    {
        $weights = [
            'multiple_faces' => 25,
            'face_swap' => 30,
            'tab_switched' => 10,
            'copy_paste' => 15,
            'external_device' => 20,
            'suspicious_behavior' => 18,
        ];

        $violations = $session->violations()->get();
        $score = 0;

        foreach ($violations as $violation) {
            $weight = $weights[$violation->violation_type] ?? 10;
            $severityMultiplier = [
                'info' => 0.25,
                'low' => 0.5,
                'medium' => 1,
                'high' => 1.5,
                'critical' => 2,
            ][$violation->severity] ?? 1;

            $score += $weight * $severityMultiplier;
        }

        $session->update(['risk_score' => min($score, 100)]);
    }
}
