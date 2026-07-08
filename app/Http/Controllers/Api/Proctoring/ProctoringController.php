<?php

namespace App\Http\Controllers\Api\Proctoring;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessViolationJob;
use App\Services\GoogleVisionService;
use App\Models\ExamAttempt;
use App\Models\ProctoringSession;
use Illuminate\Http\Request;
use App\Services\ProctoringSessionService;
use App\Services\IdentityVerificationService;
use App\Services\FaceMonitoringService;
use App\Services\ViolationService;
use Illuminate\Support\Facades\Log;

class ProctoringController extends Controller
{
    public function __construct(
        private ProctoringSessionService $sessionService,
        private IdentityVerificationService $identityService,
        private FaceMonitoringService $faceService,
        private ViolationService $violationService,
        private GoogleVisionService $googleVisionService,
    ) {
    }

    /* =========================================================
      |  INIT SESSION
      ========================================================= */
    public function initiateSession(Request $request)
    {
        $validated = $request->validate([
            'exam_attempt_id' => 'nullable|exists:exam_attempts,id',
            'session_id' => 'nullable|exists:proctoring_sessions,id',
        ]);

        $attempt = null;
        if (!empty($validated['exam_attempt_id'])) {
            $attempt = ExamAttempt::findOrFail($validated['exam_attempt_id']);
            $this->authorizeAttempt($attempt);

            // إذا كانت المحاولة منتهية، لا تنشئ جلسة جديدة
            if ($attempt->status === 'completed') {
                $existingSession = ProctoringSession::where('student_id', auth()->user()->student?->id)
                    ->where('exam_attempt_id', $attempt->id)
                    ->where('status', 'ended')
                    ->latest()
                    ->first();

                if ($existingSession) {
                    return response()->json([
                        'success' => true,
                        'session_id' => $existingSession->id,
                        'session_token' => $existingSession->session_token,
                        'status' => $existingSession->status,
                        'exam_duration' => $attempt->exam->duration_minutes ?? null,
                        'message' => 'Exam already completed',
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Exam already completed, cannot create new session',
                ], 422);
            }
        }

        $session = $this->sessionService->initiate(
            attempt: $attempt,
            request: $request,
            sessionId: $validated['session_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'session_token' => $session->session_token,
            'status' => $session->status,
            'exam_duration' => $attempt?->exam->duration_minutes ?? null,
        ]);
    }

    /* =========================================================
      |  VERIFY IDENTITY
      ========================================================= */
    public function verifyIdentity(Request $request)
    {
        $validated = $request->validate([
            'face_image' => 'required',
            'id_image' => 'required',
            'id_number' => 'required|string|min:3|max:50',
            'exam_attempt_id' => 'nullable|exists:exam_attempts,id',
        ]);

        $attempt = null;
        if (!empty($validated['exam_attempt_id'])) {
            $attempt = ExamAttempt::findOrFail($validated['exam_attempt_id']);
            $this->authorizeAttempt($attempt);
        }

        $result = $this->identityService->verify(
            user: $request->user(),
            data: $validated,
            attempt: $attempt
        );

        if (!$result->verified) {
            return response()->json([
                'verified' => false,
                'message' => $result->message ?? 'Identity verification failed'
            ], 422);
        }

        return response()->json([
            'verified' => true,
            'session_id' => $result->session_id,
            'score' => $result->score,
            'message' => $result->message
        ]);
    }



    /**
     * POST /api/proctoring/extract-id
     * يستقبل صورة البطاقة (base64) ويرجع الرقم القومي المستخرج منها.
     */
    public function extractId(Request $request)
    {
        $validated = $request->validate([
            'id_image' => 'required|string', // base64 data URL
        ]);

        // $rawText = $this->GoogleVisionService->extractRawText($validated['id_image']);
        $rawText = $this->googleVisionService->extractRawText($validated['id_image']);

        if (!$rawText) {
            return response()->json([
                'success' => false,
                'extracted_id' => null,
                'message' => 'تعذر قراءة النص من الصورة. تأكد من وضوح الصورة وحاول مرة أخرى.',
            ], 200); // 200 مع success: false، مش error، عشان ده سيناريو متوقع مش exception
        }

        // $extractedId = $this->GoogleVisionService->extractEgyptianNationalId($rawText);
        $extractedId = $this->googleVisionService->extractEgyptianNationalId($rawText);

        if (!$extractedId) {
            Log::info('OCR succeeded but no valid national ID pattern found.', [
                'raw_text_sample' => substr($rawText, 0, 200),
            ]);

            return response()->json([
                'success' => false,
                'extracted_id' => null,
                'message' => 'لم يتم العثور على رقم قومي صالح في الصورة.',
            ], 200);
        }

        return response()->json([
            'success' => true,
            'extracted_id' => $extractedId,
        ]);
    }




    /* =========================================================
     |  SESSION DETAILS
     ========================================================= */
    public function getSession($sessionId)
    {
        $session = ProctoringSession::with(['student', 'violations', 'examAttempt'])
            ->findOrFail($sessionId);

        $this->authorizeSession($session);

        $completedSkills = [];
        if ($session->examAttempt) {
            $pos = $session->examAttempt->current_position ?? [];
            $completedSkills = $pos['completed_skills'] ?? [];
        }

        return response()->json([
            'success' => true,
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'risk_score' => $session->risk_score,
                'violations_count' => $session->violations_count,
                'started_at' => $session->started_at,
                'completed_skills' => $completedSkills,
            ]
        ]);
    }

    /* =========================================================
     |  START RECORDING
     ========================================================= */
    public function startRecording($sessionId)
    {
        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        return $this->sessionService->start($session);
    }

    /* =========================================================
     |  PAUSE
     ========================================================= */
    public function pauseRecording($sessionId)
    {
        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        return $this->sessionService->pause($session);
    }

    /* =========================================================
     |  RESUME
     ========================================================= */
    public function resumeRecording($sessionId)
    {
        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        return $this->sessionService->resume($session);
    }

    /* =========================================================
     |  END SESSION
     ========================================================= */
    public function endSession(Request $request, $sessionId)
    {
        $validated = $request->validate([
            'close_reason' => 'required|in:exam_submitted,time_ended,terminated_by_proctor,connection_lost,student_left',
        ]);

        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        return $this->sessionService->end($session, $validated['close_reason']);
    }

    /* =========================================================
     |  REPORT VIOLATION
     ========================================================= */
    public function reportViolation(Request $request, $sessionId)
    {
        $validated = $request->validate([
            'violation_type' => 'required|string',
            'severity' => 'required|in:info,low,medium,high,critical',
            'description' => 'nullable|string',
            'evidence' => 'nullable|array',
        ]);

        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        $this->violationService->report($session, $validated);
        return response()->json(['success' => true]);
    }

    /* =========================================================
     |  GET VIOLATIONS
     ========================================================= */
    public function getViolations($sessionId)
    {
        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        $violations = $session->violations()->latest()->get();

        return response()->json([
            'success' => true,
            'violations_count' => $violations->count(),
            'violations' => $violations
        ]);
    }

    /* =========================================================
     |  FACE DETECTION LOG
     ========================================================= */
    // public function logFaceDetection(Request $request, $sessionId)
    // {
    //     $validated = $request->validate([
    //         'face_count' => 'required|integer',
    //         'screenshot' => 'nullable|string',
    //     ]);

    //     $session = ProctoringSession::findOrFail($sessionId);
    //     $this->authorizeSession($session);

    //     return $this->faceService->log($session, $validated);
    // }


    public function logFaceDetection(Request $request, $sessionId)
    {
        $validated = $request->validate([
            'face_count' => 'required|integer',
            'screenshot' => 'nullable|string',
        ]);

        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        return $this->faceService->log($session, $validated);
    }

    /* =========================================================
     |  GET FACE DESCRIPTOR (registered face image URL)
     |  The frontend uses this URL to compute a face-api
     |  descriptor locally for real-time identity matching.
     ========================================================= */
    public function getFaceDescriptor($sessionId)
    {
        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        $faceImageUrl = $session->device_info['face_image'] ?? null;

        if (!$faceImageUrl) {
            return response()->json([
                'success' => false,
                'face_image_url' => null,
                'message' => 'No registered face image found for this session.',
            ], 404);
        }

        // Return secure route URL instead of public storage link
        $secureUrl = route('v1.proctoring.session.face-image', ['sessionId' => $sessionId]);

        return response()->json([
            'success' => true,
            'face_image_url' => $secureUrl,
        ]);
    }

    /* =========================================================
     |  GET SECURE FACE IMAGE (binary stream bypassing public CORS)
     ========================================================= */
    public function getFaceImage($sessionId)
    {
        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        $faceImageUrl = $session->device_info['face_image'] ?? null;

        if (!$faceImageUrl) {
            abort(404, 'No registered face image found.');
        }

        // Extract the PATH portion of the storage public URL (no domain/scheme)
        // e.g. "https://example.com/api/storage" → "/api/storage"
        $publicStorageUrlPath = rtrim(parse_url(\Illuminate\Support\Facades\Storage::disk('public')->url('/'), PHP_URL_PATH) ?? '/storage', '/');

        // Get the path portion of the stored face image URL
        // e.g. "https://example.com/api/storage/proctoring/faces/x.jpg" → "/api/storage/proctoring/faces/x.jpg"
        $facePath = parse_url($faceImageUrl, PHP_URL_PATH) ?: $faceImageUrl;

        // Strip the storage public path prefix → "proctoring/faces/x.jpg"
        $path = preg_replace('#^' . preg_quote($publicStorageUrlPath, '#') . '/?#', '', $facePath);
        $path = ltrim($path, '/');

        if (!$path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            \Log::warning('Face image route could not resolve storage path.', [
                'face_image_url' => $faceImageUrl,
                'resolved_path' => $path,
                'public_storage_path' => $publicStorageUrlPath,
            ]);
            abort(404, 'Face image file does not exist on disk.');
        }

        $file = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
        $type = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path);

        return response($file, 200)->header('Content-Type', $type);
    }

    /* =========================================================
     |  AUTH HELPERS
     ========================================================= */
    private function authorizeSession(ProctoringSession $session)
    {
        $studentId = auth()->user()->student?->id;

        abort_if(
            $session->student_id !== $studentId,
            403,
            'Unauthorized session access'
        );
    }

    private function authorizeAttempt(ExamAttempt $attempt)
    {
        $user = auth()->user();
        $studentId = $user->student?->id;

        // Allow if the attempt belongs to this student OR this user directly (demo user case)
        $ownedByStudent = $studentId && $attempt->student_id === $studentId;
        $ownedByUser = $attempt->user_id === $user->id;

        abort_if(
            !$ownedByStudent && !$ownedByUser,
            403,
            'Unauthorized attempt access'
        );
    }

    /* =========================================================
      |  CLOSE SESSION (admin force close)
      ========================================================= */
    public function closeSession($sessionId)
    {
        $session = ProctoringSession::with('examAttempt')->findOrFail($sessionId);
        $this->authorizeSession($session);

        // End the proctoring session
        $this->sessionService->end($session, 'terminated_by_proctor');

        // Close the associated exam attempt if exists
        if ($session->examAttempt && $session->examAttempt->status !== 'completed') {
            $attempt = $session->examAttempt;

            // Finalize active skill + log active level + update completed_skills
            app(\App\Services\AttemptService::class)->finalizeActiveSkillAndLevelOnExit($attempt);
            $attempt->refresh();

            // Complete the attempt
            app(\App\Services\AttemptService::class)->completeAttempt($attempt);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session closed and exam terminated',
            'session' => $session->fresh()
        ]);
    }




    /* =========================================================
 |  GET SESSION STATUS (lightweight — for student polling)
 ========================================================= */
    public function getSessionStatus($sessionId)
    {
        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        return response()->json([
            'success' => true,
            'status' => $session->status,
        ]);
    }

    /* =========================================================
     |  END SESSION VIA BEACON (browser close / logout)
     |  navigator.sendBeacon لا يضمن إرسال الـ auth cookie
     |  مأمّن بـ session_token بدل Sanctum
     ========================================================= */
    public function endSessionBeacon(Request $request, $sessionId)
    {
        $data = json_decode($request->getContent(), true) ?? [];

        // تأمين بالـ session_token بدل الـ auth middleware
        $token = $data['session_token'] ?? $request->header('X-Session-Token');

        $session = ProctoringSession::find($sessionId);

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        // تحقق من الـ token
        if (!$token || $session->session_token !== $token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // لو الجلسة خلصت بالفعل، مفيش داعي نعمل حاجة
        if ($session->status === 'ended') {
            return response()->json(['success' => true, 'message' => 'Already ended']);
        }

        $reason = $data['close_reason'] ?? 'connection_lost';

        $session->update([
            'status' => 'ended',
            'recording_status' => 'completed',
            'ended_at' => now(),
            'close_reason' => $reason,
            'duration_seconds' => $session->started_at
                ? now()->diffInSeconds($session->started_at)
                : null,
        ]);

        \Log::info('Proctoring session ended via beacon', [
            'session_id' => $session->id,
            'close_reason' => $reason,
            'ended_at' => now()->toISOString(),
        ]);

        return response()->json(['success' => true]);
    }

    /* =========================================================
     |  RECORD SKILL ENTRY
     ========================================================= */
    public function recordSkill(Request $request, $sessionId)
    {
        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
        ]);

        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        // سلامة البيانات: إذا كانت هناك مهارات مفتوحة بدون exited_at، نغلقها الآن
        $activeSkills = $session->skills()->wherePivotNull('exited_at')->get();
        foreach ($activeSkills as $activeSkill) {
            if ($activeSkill->id !== (int) $validated['skill_id']) {
                $session->skills()->updateExistingPivot($activeSkill->id, [
                    'exited_at' => now(),
                ]);
            }
        }

        // syncWithoutDetaching عشان لو المهارة موجودة أصلاً ما تتضافش تاني
        $session->skills()->syncWithoutDetaching([
            $validated['skill_id'] => [
                'entered_at' => now(),
                'exited_at' => null, // ✅ Reset exited_at to null on entering/re-entering
            ],
        ]);

        return response()->json(['success' => true]);
    }

    /* =========================================================
     |  RECORD SKILL EXIT
     ========================================================= */
    public function recordSkillExit(Request $request, $sessionId)
    {
        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
        ]);

        $session = ProctoringSession::findOrFail($sessionId);
        $this->authorizeSession($session);

        $attempt = $session->examAttempt;
        $questionsAnswered = 0;
        if ($attempt) {
            $questionsAnswered = \App\Models\StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->whereHas('question', fn($q) => $q->where('skill_id', $validated['skill_id']))
                ->count();
        }

        $this->sessionService->recordSkillExit($session, (int) $validated['skill_id'], $questionsAnswered);

        return response()->json(['success' => true]);
    }



}