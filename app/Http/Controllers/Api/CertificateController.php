<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Certificate\BulkDownloadRequest;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;


class CertificateController extends Controller
{
    /**
     * Get the authenticated student's certificates.
     */
    public function index(Request $request)
    {
        $student = $request->user()->student;
        if (!$student) {
            return response()->json(['error' => 'Student profile not found.'], 404);
        }

        $certificates = Certificate::with(['attempt.exam'])
            ->where('student_id', $student->id)
            ->where('is_visible_to_student', true)
            ->latest()
            ->get();

        return CertificateResource::collection($certificates);
    }

    /**
     * Download a certificate.
     */
    public function download(Certificate $certificate)
    {
        // Check authorization (ensure student owns the certificate, or admin/teacher, or partner owns the student)
        $user = auth()->user();
        if ($user->role === 'admin' || $user->role === 'teacher') {
            // Authorized
        } elseif ($user->role === 'partner') {
            $partner = $user->partner;
            if (!$partner || $certificate->student?->partner_id !== $partner->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        } elseif ($user->role === 'student' || $user->student) {
            if ($certificate->student_id !== $user->student?->id || !$certificate->is_visible_to_student) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$certificate->file_path || !Storage::disk('public')->exists($certificate->file_path)) {
            // Re-generate if missing
            $service = app(\App\Services\CertificateService::class);
            $service->generate($certificate->attempt);
            $certificate->refresh();
        }

        if (!$certificate->file_path || !Storage::disk('public')->exists($certificate->file_path)) {
            return response()->json(['error' => 'Certificate file could not be generated.'], 500);
        }

        return Storage::disk('public')->download($certificate->file_path, "Certificate-{$certificate->certificate_number}.pdf");
    }

    /**
     * Verify a certificate publically.
     */
    public function verify($code, Request $request)
    {
        $query = Certificate::with(['student.user', 'attempt.exam'])
            ->where('verification_code', $code);

        $user = $this->resolveOptionalUser($request);

        $isPrivileged = $user && in_array($user->role, ['admin', 'teacher']);

        // لو Partner، لازم كمان الشهادة تبقى تابعة لطلابه
        if ($user && $user->role === 'partner') {
            $partner = $user->partner;
            if ($partner) {
                $query->whereHas('student', function ($q) use ($partner) {
                    $q->where('partner_id', $partner->id);
                });
                $isPrivileged = true;
            }
        }

        if (!$isPrivileged) {
            $query->where('is_visible_to_student', true);
        }

        $certificate = $query->first();

        if (!$certificate) {
            return response()->json(['error' => 'Certificate not found or not visible'], 404);
        }

        $service = app(\App\Services\CertificateService::class);

        // Build rendered template HTML for the verify page (same as PDF but without DomPDF wrapper)
        $renderedHtml = null;
        try {
            $renderedHtml = $service->renderForVerifyPage($certificate);
        } catch (\Throwable $e) {
            // non-fatal — frontend will fall back to legacy layout
        }

        $template = $certificate->template ?: null;
        $dateFormat = $service->getCertificateDateFormat($template);
        $latestFinishedAt = $certificate->attempt->attemptSkills()->whereNotNull('finished_at')->max('finished_at');
        $overallDateCarbon = $latestFinishedAt ? \Carbon\Carbon::parse($latestFinishedAt) : ($certificate->issue_date ?: now());
        $overallDate = $service->formatCertificateDate($overallDateCarbon, $dateFormat);

        return response()->json([
            'valid' => true,
            'student_name' => $certificate->student->user->first_name . ' ' . $certificate->student->user->last_name,
            'exam_name' => $certificate->attempt->exam->name,
            'score' => $certificate->score,
            'total_points' => round(($certificate->score / 100) * 900),
            'cefr' => $service->mapToCefr($certificate->score),
            'actfl' => $service->mapToActfl($certificate->score),
            'issue_date' => $overallDate,
            'certificate_number' => $certificate->certificate_number,
            'rendered_html' => $renderedHtml,
            'skills' => $certificate->attempt->attemptSkills()->with('skill')->get()->map(function ($s) use ($service, $dateFormat, $overallDateCarbon, $overallDate) {
                return [
                    'name' => $s->skill->name,
                    'score' => $s->score,
                    'points' => round(($s->score / 100) * 900),
                    'cefr' => $service->mapToCefr($s->score),
                    'actfl' => $service->mapToActfl($s->score),
                    'date' => $service->formatCertificateDate($s->finished_at ?: $overallDateCarbon, $dateFormat)
                ];
            })
        ]);
    }

    /**
     * Admin or Partner: Manually create (or regenerate) a certificate for an attempt.
     *
     * If the attempt is not yet completed (e.g. still "ongoing" after productive-skill
     * grading was done without the auto-complete firing), we mark it completed first so
     * the report page reflects the correct status.
     */
    public function createForAttempt(Request $request, ExamAttempt $attempt)
    {
        $user = $request->user();

        // Partner ownership check
        if ($user->role === 'partner') {
            $partner = $user->partner;
            if (!$partner || $attempt->student?->partner_id !== $partner->id) {
                return response()->json(['error' => 'Unauthorized: student does not belong to your partner account.'], 403);
            }
        }

        if (!$attempt->student) {
            return response()->json(['error' => 'Attempt has no associated student.'], 422);
        }

        try {
            // Auto-complete the attempt if it was never marked as completed.
            // This covers edge cases where the student finished all skills but the
            // status was never updated (e.g. productive skills graded without the
            // auto-complete firing, or old attempts created before this logic existed).
            if ($attempt->status !== 'completed') {
                $attempt->update([
                    'status'      => 'completed',
                    'finished_at' => $attempt->finished_at ?? now(),
                ]);
                $attempt->refresh();
            }

            $service = app(\App\Services\CertificateService::class);
            $certificate = $service->generate($attempt);

            // load relationships for the response
            $certificate->load(['student.user', 'attempt.exam']);

            return response()->json([
                'message'         => 'Certificate created successfully.',
                'certificate'     => new CertificateResource($certificate),
                'attempt_status'  => $attempt->status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Admin/Teacher: List all certificates.
     */
    public function adminIndex(Request $request)
    {
        $query = Certificate::with(['student.user', 'student.partner', 'attempt.exam']);

        if ($request->partner_id) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('partner_id', $request->partner_id);
            });
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('student.user', function ($sq) use ($request) {
                    $sq->where('first_name', 'like', "%{$request->search}%")
                        ->orWhere('last_name', 'like', "%{$request->search}%")
                        ->orWhere('username', 'like', "%{$request->search}%");
                })->orWhere('certificate_number', 'like', "%{$request->search}%");
            });
        }

        if ($request->date_from) {
            $query->whereDate('issue_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('issue_date', '<=', $request->date_to);
        }

        return CertificateResource::collection($query->latest()->paginate(20));
    }

    /**
     * Admin/Partner: Bulk download certificates as ZIP archive.
     */
    public function bulkDownload(BulkDownloadRequest $request)
    {
        $user = $request->user();

        $validated = $request->validated();

        $query = Certificate::with(['student.user', 'attempt.exam']);

        if ($user->role === 'partner') {
            $partner = $user->partner;
            if (!$partner) {
                return response()->json(['error' => 'Partner profile not found.'], 404);
            }
            $query->whereHas('student', fn($q) => $q->where('partner_id', $partner->id));
        } elseif ($request->partner_id) {
            $query->whereHas('student', fn($q) => $q->where('partner_id', $request->partner_id));
        }

        if (!empty($request->certificate_ids)) {
            $query->whereIn('id', $request->certificate_ids);
        }

        $certificates = $query->get();

        if ($certificates->isEmpty()) {
            return response()->json(['error' => 'No certificates found for download.'], 404);
        }

        $service = app(\App\Services\CertificateService::class);
        $zipFileName = 'Certificates_' . time() . '.zip';
        $tempZipDir = storage_path('app/temp');
        $tempZipPath = $tempZipDir . '/' . $zipFileName;

        if (!file_exists($tempZipDir)) {
            mkdir($tempZipDir, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['error' => 'Failed to create ZIP file.'], 500);
        }

        foreach ($certificates as $certificate) {
            if (!$certificate->file_path || !Storage::disk('public')->exists($certificate->file_path)) {
                try {
                    $service->generate($certificate->attempt);
                    $certificate->refresh();
                } catch (\Exception $e) {
                    continue;
                }
            }

            if ($certificate->file_path && Storage::disk('public')->exists($certificate->file_path)) {
                $fullPath = Storage::disk('public')->path($certificate->file_path);
                $firstName = $certificate->student?->user?->first_name ?? 'Student';
                $lastName = $certificate->student?->user?->last_name ?? '';
                $cleanName = preg_replace('/[^\w\s-]/u', '', trim("{$firstName}_{$lastName}"));
                $fileNameInZip = "Certificate_{$cleanName}_{$certificate->certificate_number}.pdf";
                $zip->addFile($fullPath, $fileNameInZip);
            }
        }

        $zip->close();

        if (!file_exists($tempZipPath)) {
            return response()->json(['error' => 'ZIP file could not be generated.'], 500);
        }

        return response()->download($tempZipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * Admin: Toggle certificate visibility for student.
     */
    public function toggleVisibility(Certificate $certificate)
    {
        $certificate->update([
            'is_visible_to_student' => !$certificate->is_visible_to_student,
        ]);

        return response()->json([
            'message' => 'Visibility updated.',
            'is_visible_to_student' => $certificate->is_visible_to_student,
        ]);
    }

    /**
     * Admin: Delete a certificate and its stored file.
     */
    public function destroy(Certificate $certificate)
    {
        $user = auth()->user();

        // Only staff (admin/teacher) or partner owning the student may delete
        if ($user->role === 'admin' || $user->role === 'teacher') {
            // allowed
        } elseif ($user->role === 'partner') {
            $partner = $user->partner;
            if (!$partner || $certificate->student?->partner_id !== $partner->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete stored file if exists
        if ($certificate->file_path && Storage::disk('public')->exists($certificate->file_path)) {
            Storage::disk('public')->delete($certificate->file_path);
        }

        $certificate->delete();

        return response()->json(['message' => 'Certificate deleted successfully.']);
    }

    /**
     * Partner: List certificates for students belonging to this partner.
     */
    public function partnerIndex(Request $request)
    {
        $user = $request->user();
        $partner = $user->partner;

        if (!$partner) {
            return response()->json(['error' => 'Partner profile not found.'], 404);
        }

        $query = Certificate::with(['student.user', 'attempt.exam'])
            ->whereHas('student', function ($q) use ($partner) {
                $q->where('partner_id', $partner->id);
            });

        if ($request->search) {
            $query->where(function ($outer) use ($request) {
                $outer->whereHas('student.user', function ($q) use ($request) {
                    $q->where('first_name', 'like', "%{$request->search}%")
                        ->orWhere('last_name', 'like', "%{$request->search}%")
                        ->orWhere('username', 'like', "%{$request->search}%");
                })->orWhere('certificate_number', 'like', "%{$request->search}%");
            });
        }

        return response()->json($query->latest()->paginate(20));
    }


    /**
     * Resolve user from bearer token manually, without forcing auth (route stays public).
     */
    private function resolveOptionalUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        return $accessToken?->tokenable;
    }
}
