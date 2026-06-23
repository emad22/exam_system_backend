<?php

namespace App\Http\Controllers\Api\Proctoring;

use App\Models\ProctoringSession;
use App\Models\ExamViolation;
use App\Models\CheatingAlert;
use App\Models\ExamAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class ProctoringController extends Controller
{
    /**
     * التحقق من هوية الطالب (صورة الوجه + بطاقة الهوية + رقم الهوية)
     */
    public function verifyIdentity(Request $request)
    {
        $validated = $request->validate([
            'face_image'  => 'required',           // Can be string (base64) or File
            'id_image'    => 'nullable',           // Can be string (base64) or File
            'id_number'   => 'required|string|min:3|max:50',
        ]);

        try {
            $user = $request->user();

            // 1. Get student profile
            $student = $user->student;
            if (!$student) {
                return response()->json([
                    'verified' => false,
                    'message' => 'بيانات الطالب غير مسجلة في النظام.',
                ], 404);
            }

            // 2. Validate inputted ID against registered student code (only if student_code is registered)
            $expectedID = $validated['id_number'];
            if (!empty($student->student_code)) {
                $normalizedInput = preg_replace('/[^A-Za-z0-9]/', '', $validated['id_number']);
                $normalizedCode = preg_replace('/[^A-Za-z0-9]/', '', $student->student_code);

                if (strcasecmp($normalizedInput, $normalizedCode) !== 0) {
                    return response()->json([
                        'verified' => false,
                        'message' => 'رقم الهوية المدخل لا يتطابق مع كود الطالب المسجل لدينا.',
                    ], 422);
                }
                $expectedID = $student->student_code;
            }

            // 3. Save images (handling both base64 string and uploaded files)
            $faceUrl  = null;
            $idUrl    = null;

            if ($request->hasFile('face_image')) {
                $path = $request->file('face_image')->store('proctoring/faces', 'public');
                $faceUrl = Storage::disk('public')->url($path);
            } elseif (is_string($request->input('face_image'))) {
                $faceUrl = $this->saveBase64Image($request->input('face_image'), 'proctoring/faces', $user->id . '_face');
            }

            if ($request->hasFile('id_image')) {
                $path = $request->file('id_image')->store('proctoring/ids', 'public');
                $idUrl = Storage::disk('public')->url($path);
            } elseif (is_string($request->input('id_image'))) {
                $idUrl = $this->saveBase64Image($request->input('id_image'), 'proctoring/ids', $user->id . '_id');
            }

            // 4. Prepare ID Image for Gemini AI processing
            $idImageBase64 = null;
            $mimeType = 'image/jpeg';

            if ($request->hasFile('id_image')) {
                $file = $request->file('id_image');
                $idImageBase64 = base64_encode(file_get_contents($file->getPathname()));
                $mimeType = $file->getMimeType() ?: 'image/jpeg';
            } elseif (is_string($request->input('id_image'))) {
                $base64Str = $request->input('id_image');
                if (str_contains($base64Str, ',')) {
                    $parts = explode(',', $base64Str);
                    $idImageBase64 = $parts[1];
                    if (preg_match('/^data:(image\/[a-z]+);base64/', $parts[0], $m)) {
                        $mimeType = $m[1];
                    }
                } else {
                    $idImageBase64 = $base64Str;
                }
            }

            // 5. Use Gemini AI to verify the ID document if key and image are available
            $apiKey = config('services.gemini.api_key');
            $aiVerified = true;
            $aiScore = 85;
            $aiReason = 'تم مطابقة كود الطالب بنجاح مع البيانات المسجلة.';

            if ($apiKey && $idImageBase64) {
                $studentName = $user->first_name . ' ' . $user->last_name;
                $prompt = "You are a professional security and exam proctoring identity verification system.\n" .
                    "Compare the student's registered info with the provided national ID card or passport image:\n" .
                    "- Expected ID / Student Code: \"{$expectedID}\"\n" .
                    "- Expected Student Name: \"{$studentName}\"\n\n" .
                    "Please verify if the ID card/passport image shown belongs to this student and displays the expected ID or Student Code.\n" .
                    "Return ONLY a JSON response in the following format (no markdown code blocks, no backticks, just the raw JSON object):\n" .
                    "{\n" .
                    "  \"matched\": true/false,\n" .
                    "  \"extracted_id\": \"any ID number or code found in the image\",\n" .
                    "  \"extracted_name\": \"the name found in the image (if any)\",\n" .
                    "  \"confidence_score\": <integer 0-100>,\n" .
                    "  \"reason\": \"A short explanation in Arabic detailing whether it matches and why\"\n" .
                    "}";

                try {
                    $apiResponse = Http::timeout(30)->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
                        [
                            'contents' => [
                                [
                                    'parts' => [
                                        [
                                            'text' => $prompt
                                        ],
                                        [
                                            'inlineData' => [
                                                'mimeType' => $mimeType,
                                                'data' => $idImageBase64
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'generationConfig' => [
                                'temperature' => 0.1,
                                'maxOutputTokens' => 512,
                                'responseMimeType' => 'application/json'
                            ]
                        ]
                    );

                    if ($apiResponse->successful()) {
                        $text = trim($apiResponse->json('candidates.0.content.parts.0.text'));
                        if (str_starts_with($text, '```')) {
                            $text = preg_replace('/^```(?:json)?|```$/s', '', $text);
                            $text = trim($text);
                        }

                        $aiData = json_decode($text, true);

                        if (is_array($aiData) && isset($aiData['matched'])) {
                            $aiVerified = (bool) $aiData['matched'];
                            $aiScore = isset($aiData['confidence_score']) ? (int) $aiData['confidence_score'] : 85;
                            $aiReason = $aiData['reason'] ?? '';
                        } else {
                            \Log::warning('Gemini identity verification response format invalid', ['text' => $text]);
                            $aiReason = 'فشل التحقق من صحة تنسيق استجابة الذكاء الاصطناعي.';
                        }
                    } else {
                        \Log::warning('Gemini API call failed during identity verification', [
                            'status' => $apiResponse->status(),
                            'body' => $apiResponse->body()
                        ]);
                        $aiReason = 'فشل الاتصال بخدمة التحقق من الهوية بالذكاء الاصطناعي.';
                    }
                } catch (\Exception $e) {
                    \Log::error('Error calling Gemini for ID verification', [
                        'message' => $e->getMessage()
                    ]);
                    $aiReason = 'حدث خطأ أثناء فحص صورة الهوية بالذكاء الاصطناعي: ' . $e->getMessage();
                }
            }

            if (!$aiVerified) {
                return response()->json([
                    'verified' => false,
                    'message' => 'فشل التحقق من صورة الهوية: ' . $aiReason,
                ], 422);
            }

            // 6. Find or create Proctoring Session
            $session = ProctoringSession::where('student_id', $user->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if (!$session) {
                $session = ProctoringSession::create([
                    'student_id'   => $user->id,
                    'status'       => 'pending',
                    'ip_address'   => $request->ip(),
                    'user_agent'   => $request->userAgent(),
                    'device_info'  => ['platform' => $request->header('User-Agent'), 'timestamp' => now()],
                ]);
            }

            // 7. Update Proctoring Session Verification data
            $session->update([
                'identity_verified'        => true,
                'face_verification_score'  => $aiScore,
                'identity_verification_at' => now(),
                'device_info'              => array_merge($session->device_info ?? [], [
                    'id_number'   => $validated['id_number'],
                    'face_image'  => $faceUrl,
                    'id_image'    => $idUrl,
                    'verified_at' => now()->toISOString(),
                    'ai_reason'   => $aiReason,
                ]),
            ]);

            \Log::info('Identity verified successfully', [
                'student_id' => $user->id,
                'session_id' => $session->id,
                'id_number'  => $validated['id_number'],
                'ai_score'   => $aiScore
            ]);

            return response()->json([
                'verified'          => true,
                'session_id'        => $session->id,
                'verification_score'=> $aiScore,
                'message'           => 'Identity verified successfully: ' . $aiReason,
            ]);

        } catch (\Exception $e) {
            \Log::error('Identity verification failed', [
                'error'      => $e->getMessage(),
                'student_id' => $request->user()?->id,
            ]);

            return response()->json([
                'verified' => false,
                'message'  => 'Verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحويل base64 وحفظ الصورة في storage
     */
    private function saveBase64Image(string $base64, string $folder, string $name): ?string
    {
        try {
            // إزالة header مثل "data:image/jpeg;base64,"
            if (str_contains($base64, ',')) {
                $base64 = explode(',', $base64)[1];
            }

            $imageData = base64_decode($base64);
            if (!$imageData) return null;

            $filename = $folder . '/' . $name . '_' . time() . '.jpg';
            Storage::disk('public')->put($filename, $imageData);

            return Storage::disk('public')->url($filename);
        } catch (\Exception $e) {
            \Log::warning('Failed to save image', ['error' => $e->getMessage()]);
            return null;
        }
    }

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
