<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return response()->json($certificates);
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

        return response()->json([
            'valid' => true,
            'student_name' => $certificate->student->user->first_name . ' ' . $certificate->student->user->last_name,
            'exam_name' => $certificate->attempt->exam->name,
            'score' => $certificate->score,
            'total_points' => round(($certificate->score / 100) * 900),
            'cefr' => $service->mapToCefr($certificate->score),
            'actfl' => $service->mapToActfl($certificate->score),
            'issue_date' => $certificate->issue_date->format('M d, Y'),
            'certificate_number' => $certificate->certificate_number,
            'skills' => $certificate->attempt->attemptSkills()->with('skill')->get()->map(function ($s) use ($service) {
                return [
                    'name' => $s->skill->name,
                    'score' => $s->score,
                    'points' => round(($s->score / 100) * 900),
                    'cefr' => $service->mapToCefr($s->score),
                    'actfl' => $service->mapToActfl($s->score),
                    'date' => $s->finished_at ? $s->finished_at->format('d M. Y') : now()->format('d M. Y')
                ];
            })
        ]);
    }

    /**
     * Admin or Partner: Manually create (or regenerate) a certificate for an attempt.
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
            $service = app(\App\Services\CertificateService::class);
            $certificate = $service->generate($attempt);

            // load relationships for the response
            $certificate->load(['student.user', 'attempt.exam']);

            return response()->json([
                'message' => 'Certificate created successfully.',
                'certificate' => $certificate,
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
        $query = Certificate::with(['student.user', 'attempt.exam']);

        if ($request->search) {
            $query->whereHas('student.user', function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('username', 'like', "%{$request->search}%");
            })->orWhere('certificate_number', 'like', "%{$request->search}%");
        }

        return response()->json($query->latest()->paginate(20));
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
