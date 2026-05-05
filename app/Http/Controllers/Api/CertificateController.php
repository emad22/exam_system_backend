<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            ->latest()
            ->get();

        return response()->json($certificates);
    }

    /**
     * Download a certificate.
     */
    public function download(Certificate $certificate)
    {
        // Check authorization (ensure student owns the certificate)
        $user = auth()->user();
        if ($user->role !== 'admin' && $user->role !== 'teacher') {
            if ($certificate->student_id !== $user->student?->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
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
    public function verify($code)
    {
        $certificate = Certificate::with(['student.user', 'attempt.exam'])
            ->where('verification_code', $code)
            ->first();

        if (!$certificate) {
            return response()->json(['error' => 'Invalid verification code.'], 404);
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
            'skills' => $certificate->attempt->attemptSkills()->with('skill')->get()->map(function($s) use ($service) {
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
     * Admin/Teacher: List all certificates.
     */
    public function adminIndex(Request $request)
    {
        $query = Certificate::with(['student.user', 'attempt.exam']);

        if ($request->search) {
            $query->whereHas('student.user', function($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%");
            })->orWhere('certificate_number', 'like', "%{$request->search}%");
        }

        return response()->json($query->latest()->paginate(20));
    }
}
