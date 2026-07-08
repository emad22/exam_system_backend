<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ProctoringSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IdentityVerificationService
{
    public function __construct(protected GoogleVisionService $visionService)
    {
    }

    public function verify($user, array $data, ?ExamAttempt $attempt = null): object
    {
        $student = $user->student;

        if (!$student) {
            return (object) [
                'verified' => false,
                'session_id' => null,
                'score' => 0,
                'message' => 'Student record not found'
            ];
        }

        // 1. رقم الهوية vs الداتابيز
        $studentCode = $this->normalize($data['id_number']);
        $dbCode = $this->normalize($student->student_code ?? '');
        $codeMatch = $dbCode === $studentCode;

        // 2. احتفظ بالصور فوراً في التخزين حتى لو فشل التحقق النهائي لاحقاً
        $faceUrl = $this->storeImage($data['face_image'], 'proctoring/faces', $user->id . '_face');
        $idUrl = !empty($data['id_image']) ? $this->storeImage($data['id_image'], 'proctoring/ids', $user->id . '_id') : null;

        if (!$faceUrl) {
            \Log::warning('Failed to store face image during identity verification', [
                'user_id' => $user->id,
                'student_id' => $student->id,
            ]);
        }
        if (!empty($data['id_image']) && !$idUrl) {
            \Log::warning('Failed to store ID image during identity verification', [
                'user_id' => $user->id,
                'student_id' => $student->id,
            ]);
        }

        $faceVsIdScore = isset($data['face_vs_id_score']) ? (int) $data['face_vs_id_score'] : null;
        $faceVsIdDistance = isset($data['face_vs_id_distance']) ? (float) $data['face_vs_id_distance'] : null;

        // Sanity check
        if ($faceVsIdScore !== null && $faceVsIdDistance !== null) {
            $expectedScore = (int) round((1 - min($faceVsIdDistance, 1)) * 100);
            if (abs($expectedScore - $faceVsIdScore) > 2) {
                return (object) [
                    'verified' => false,
                    'session_id' => null,
                    'score' => 0,
                    'message' => 'Invalid face verification data'
                ];
            }
        }

        // بعد الـ sanity check وقبل القرار النهائي
        $ocrMatch = true;
        if (!empty($data['id_image'])) {
            $ocrResult = $this->extractIdFromImage($data['id_image']);
            $extractedId = $this->normalize($ocrResult['extracted_id'] ?? '');

            \Log::info('OCR result', [
                'ocr_result' => $ocrResult,
                'extracted_id' => $extractedId,
                'student_code' => $studentCode,
                'ocr_match' => $ocrMatch,
            ]);

            if (!empty($extractedId)) {
                $ocrMatch = ($extractedId === $studentCode);
            }
        }

        $faceMatch = $faceVsIdDistance !== null ? $faceVsIdDistance < 0.6 : true;
        $verified = $codeMatch && $faceMatch && $ocrMatch;

        try {
            $existingPendingSession = ProctoringSession::where('student_id', $student->id)
                ->whereIn('status', ['pending', 'active', 'paused'])
                ->latest()
                ->first();

            $session = $existingPendingSession;

            if (!$session || $session->status === 'ended') {
                $session = ProctoringSession::create([
                    'student_id' => $student->id,
                    'exam_attempt_id' => null,
                    'status' => 'pending',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'session_token' => Str::random(64)
                ]);
            }

            $session->update([
                'identity_verified' => $verified,
                'face_verification_score' => $faceVsIdScore ?? 0,
                'identity_verification_at' => now(),
                'device_info' => array_merge($session->device_info ?? [], [
                    'id_number' => $data['id_number'],
                    'face_image' => $faceUrl,
                    'id_image' => $idUrl,
                    'face_vs_id_distance' => $faceVsIdDistance,
                    'os' => $this->parseOS(request()->userAgent()),
                ]),
                'browser_info' => ['name' => $this->parseBrowser(request()->userAgent())],
            ]);

            if (!$codeMatch) {
                return (object) [
                    'verified' => false,
                    'session_id' => $session->id,
                    'score' => 0,
                    'message' => 'ID number does not match our records'
                ];
            }

            return (object) [
                'verified' => $verified,
                'session_id' => $session->id,
                'score' => $faceVsIdScore ?? 0,
                'message' => $verified ? 'Identity verified successfully' : 'Identity verification failed',
            ];

        } catch (\Exception $e) {
            \Log::error('IdentityVerification error: ' . $e->getMessage());
            return (object) [
                'verified' => false,
                'session_id' => null,
                'score' => 0,
                'message' => 'Server error during verification'
            ];
        }
    }


    /**
     * Public OCR entrypoint — called by the dedicated /extract-id endpoint.
     */
    public function extractIdPublic(string $image): array
    {
        return $this->extractIdFromImage($image);
    }

    /**
     * يستخرج الرقم القومي من صورة البطاقة باستخدام Google Vision API.
     * استبدلنا هنا منطق Gemini القديم (كان معطل ومش بيبارس الرد أصلاً).
     */
    private function extractIdFromImage($image): array
    {
        if ($image instanceof \Illuminate\Http\UploadedFile) {
            $imageContent = file_get_contents($image->getRealPath());
            $image = base64_encode($imageContent);
        } elseif (is_string($image) && preg_match('#^https?://#i', $image)) {
            $imageContent = @file_get_contents($image);
            $image = $imageContent ? base64_encode($imageContent) : '';
        }

        if (empty($image)) {
            return [
                'extracted_id' => '',
                'confidence_score' => 0,
            ];
        }

        $rawText = $this->visionService->extractRawText($image);

        \Log::info('Google Vision OCR — raw text', [
            'raw_text' => $rawText,
        ]);

        if (!$rawText) {
            \Log::info('Google Vision OCR returned no text.');
            return [
                'extracted_id' => '',
                'confidence_score' => 0,
            ];
        }

        $extractedId = $this->visionService->extractEgyptianNationalId($rawText);

        \Log::info('Google Vision OCR — extracted national ID', [
            'extracted_id' => $extractedId,
        ]);

        if (!$extractedId) {
            \Log::info('Google Vision OCR: no valid national ID pattern found.', [
                'raw_text_sample' => substr($rawText, 0, 200),
            ]);

            return [
                'extracted_id' => '',
                'confidence_score' => 0,
            ];
        }

        return [
            'extracted_id' => $extractedId,
            'confidence_score' => 95, // Google Vision بيرجع bounding boxes مش confidence score مباشر للنص الكامل،
            // فحطينا رقم ثابت تقريبي. ممكن نحسبها بدقة أكتر بعدين لو احتجت.
        ];
    }

    private function storeImage($image, string $folder, string $name): ?string
    {
        try {
            // Case 1: actual uploaded file
            if ($image instanceof \Illuminate\Http\UploadedFile) {
                $extension = $image->getClientOriginalExtension() ?: 'jpg';
                $fileName = $name . '_' . time() . '.' . $extension;
                $path = Storage::disk('public')->putFileAs($folder, $image, $fileName);
                return $path ? Storage::disk('public')->url($path) : null;
            }

            // Case 2: already a URL (http/https) — download and re-store it
            if (is_string($image) && preg_match('#^https?://#i', $image)) {
                $binary = @file_get_contents($image);
                if (!$binary) {
                    \Log::warning('storeImage: could not download image from URL', ['url' => $image]);
                    return $image;
                }
                $fileName = $folder . '/' . $name . '_' . time() . '.jpg';
                $written = Storage::disk('public')->put($fileName, $binary);
                if (!$written) {
                    \Log::error('storeImage: failed to write downloaded image to disk (check folder permissions)', [
                        'fileName' => $fileName,
                        'folder' => $folder,
                    ]);
                    return null;
                }
                return Storage::disk('public')->url($fileName);
            }

            // Case 3: base64 data URL or raw base64 string
            $base64 = $this->toBase64($image);
            $binary = base64_decode($base64, true);
            if (!$binary) {
                \Log::warning('storeImage: base64_decode returned false', ['name' => $name]);
                return null;
            }

            $fileName = $folder . '/' . $name . '_' . time() . '.jpg';
            $written = Storage::disk('public')->put($fileName, $binary);
            if (!$written) {
                \Log::error('storeImage: failed to write base64 image to disk (check folder permissions)', [
                    'fileName' => $fileName,
                    'folder' => $folder,
                ]);
                return null;
            }
            return Storage::disk('public')->url($fileName);
        } catch (\Exception $e) {
            \Log::error('storeImage failed', [
                'message' => $e->getMessage(),
                'folder' => $folder,
                'name' => $name,
            ]);
            return null;
        }
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', strtoupper($value));
    }

    private function toBase64($input): string
    {
        if (str_contains($input, ',')) {
            return explode(',', $input)[1];
        }
        return $input;
    }

    private function parseOS(string $ua): string
    {
        if (str_contains($ua, 'Windows'))
            return 'Windows';
        if (str_contains($ua, 'Mac'))
            return 'macOS';
        if (str_contains($ua, 'Linux'))
            return 'Linux';
        if (str_contains($ua, 'Android'))
            return 'Android';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))
            return 'iOS';
        return 'Unknown';
    }

    private function parseBrowser(string $ua): string
    {
        if (str_contains($ua, 'Chrome') && !str_contains($ua, 'Edg'))
            return 'Chrome';
        if (str_contains($ua, 'Firefox'))
            return 'Firefox';
        if (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome'))
            return 'Safari';
        if (str_contains($ua, 'Edg'))
            return 'Edge';
        return 'Unknown';
    }
}