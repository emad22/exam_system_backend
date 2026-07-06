<?php

namespace App\Services;

use App\Models\ProctoringSession;
use App\Models\ExamViolation;
use App\Models\FaceDetectionLog;
use App\Jobs\CompareFacesJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FaceMonitoringService
{
    public function log(ProctoringSession $session, array $data): JsonResponse
    {
        $faceCount = (int) $data['face_count'];

        // ✅ الحالة الطبيعية - لا تسجل خالص
        if ($faceCount === 1) {
            // لو حان وقت الـ AI check، حطه في queue بدون ما يبلوك الـ response
            if ($this->shouldRunCheck($session)) {
                dispatch(new CompareFacesJob($session->id, $data['screenshot'] ?? null));
            }

            return response()->json(['success' => true, 'logged' => false]);
        }

        // Cooldown check on backend to prevent DB spam (e.g. from multiple open tabs or high frequency requests)
        $recentLogExists = FaceDetectionLog::where('proctoring_session_id', $session->id)
            ->where('face_count', $faceCount)
            ->where('timestamp', '>=', now()->subSeconds(10))
            ->exists();

        if ($recentLogExists) {
            return response()->json([
                'success' => true,
                'logged' => false,
                'message' => 'Skipped logging due to backend rate limiting'
            ]);
        }

        // ⚠️ مشكلة - سجل بس في الحالات دي
        $violationType = $faceCount === 0 ? 'face_not_visible' : 'multiple_faces';
        $severity = $faceCount === 0 ? 'high' : 'medium';
        $description = $faceCount === 0
            ? 'No face detected in frame'
            : "Multiple faces detected: {$faceCount}";

        // Screenshot بس في حالات المخالفة
        $screenshotUrl = null;
        if (!empty($data['screenshot'])) {
            $screenshotUrl = $this->storeImage(
                $data['screenshot'],
                'proctoring/logs/' . $session->id,
                'face_' . time() . '_' . Str::random(5)
            );
        }

        FaceDetectionLog::create([
            'proctoring_session_id' => $session->id,
            'student_id' => $session->student_id,
            'face_count' => $faceCount,
            'secondary_face_detected' => $faceCount > 1,
            'face_lost' => $faceCount === 0,
            'screenshot_url' => $screenshotUrl,
            'timestamp' => now(),
        ]);

        app(ViolationService::class)->report($session, [
            'violation_type' => $violationType,
            'severity' => $severity,
            'description' => $description,
            'evidence' => $screenshotUrl ? ['screenshot' => $screenshotUrl] : [],
        ]);

        return response()->json([
            'success' => true,
            'logged' => true,
            'violation' => $violationType
        ]);
    }

    /* =========================================================
     |  AI CHECK - shouldRunCheck بس
     ========================================================= */
    public function shouldRunCheck(ProctoringSession $session): bool
    {
        $last = $session->device_info['last_face_match_at'] ?? null;

        if (!$last)
            return true;

        try {
            return now()->diffInSeconds($last) >= 60;
        } catch (\Exception $e) {
            return true;
        }
    }

    /* =========================================================
     |  compareFaces - بتتنادى من الـ Job مش من هنا
     ========================================================= */
    public function compareFaces(ProctoringSession $session, ?string $screenshotBase64): void
    {
        $apiKey = config('services.gemini.api_key');
        if (!$apiKey || !$screenshotBase64)
            return;

        $registeredFaceUrl = $session->device_info['face_image'] ?? null;
        if (!$registeredFaceUrl)
            return;

        $registeredBase64 = $this->loadRegisteredFace($registeredFaceUrl);
        if (!$registeredBase64)
            return;

        $prompt = "Compare these two faces:
Image 1 = registered student
Image 2 = webcam capture
Return ONLY JSON: {\"matched\": true/false, \"confidence_score\": 0, \"reason\": \"explanation\"}";

        try {
            $response = Http::timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $registeredBase64]],
                                ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $this->toBase64($screenshotBase64)]],
                            ]
                        ]
                    ],
                    'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json']
                ]
            );

            if (!$response->successful())
                return;

            $result = json_decode(
                $this->cleanJson($response->json('candidates.0.content.parts.0.text')),
                true
            );

            if (!isset($result['matched']))
                return;

            // Update session
            $session->update([
                'device_info' => array_merge($session->device_info ?? [], [
                    'last_face_match_at' => now()->toISOString(),
                    'last_face_match_ok' => $result['matched'],
                    'last_face_match_score' => $result['confidence_score'] ?? 0,
                ])
            ]);

            // Violation لو مش matched
            if (!$result['matched'] || ($result['confidence_score'] ?? 0) < 70) {
                $violation = app(ViolationService::class)->report($session, [
                    'violation_type' => 'face_swap',
                    'severity' => 'critical',
                    'description' => 'Face mismatch: ' . ($result['reason'] ?? ''),
                    'evidence' => [],
                ]);

                app(ViolationService::class)->triggerAlert(
                    $session,
                    ExamViolation::find($violation->id)
                );
            }

        } catch (\Exception $e) {
            \Log::error('Face comparison failed', ['error' => $e->getMessage()]);
        }
    }

    // =========================================================
    //  Helpers
    // =========================================================
    private function loadRegisteredFace(string $url): ?string
    {
        try {
            $disk = Storage::disk('public');
            $prefix = $disk->url('');
            $path = str_replace($prefix . '/', '', $url);

            return $disk->exists($path) ? base64_encode($disk->get($path)) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function storeImage($input, string $folder, string $name): ?string
    {
        try {
            $binary = base64_decode($this->toBase64($input), true);
            if (!$binary)
                return null;

            $file = $folder . '/' . $name . '.jpg';
            Storage::disk('public')->put($file, $binary);

            return Storage::disk('public')->url($file);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function toBase64($input): string
    {
        return str_contains($input, ',') ? explode(',', $input)[1] : $input;
    }

    private function cleanJson(string $text): string
    {
        return trim(preg_replace('/^```(?:json)?|```$/', '', trim($text)));
    }
}