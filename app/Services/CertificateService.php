<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ExamAttempt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    /**
     * Generate a certificate for a given exam attempt.
     */
    public function generate(ExamAttempt $attempt)
    {
        // 1. Check if certificate already exists
        $certificate = $attempt->certificate;

        // Determine template preference:
        // - If a certificate exists and already has a template_id, use that template.
        // - Otherwise use the default template (is_default) or the first available.
        $template = null;
        if ($certificate && $certificate->template_id) {
            $template = CertificateTemplate::find($certificate->template_id);
        }

        if (!$template) {
            $template = CertificateTemplate::where('is_default', true)->first()
                ?? CertificateTemplate::first();
        }

        if (!$template) {
            throw new \Exception("No certificate template found. Please create one in Admin panel.");
        }

        if (!$certificate) {
            // 3. Prepare Data
            $certificateNumber = $this->generateCertificateNumber();
            $verificationCode = Str::random(12);
            $student = $attempt->student;
            $score = $attempt->overall_score;

            // 4. Create the Record
            $certificate = Certificate::create([
                'student_id' => $student->id,
                'exam_attempt_id' => $attempt->id,
                'template_id' => $template->id,
                'certificate_number' => $certificateNumber,
                'score' => $score,
                'issue_date' => now(),
                'verification_code' => $verificationCode,
            ]);
        }

        // 5. Generate PDF
        $pdfPath = $this->renderAndSavePdf($certificate, $template, $attempt);

        $certificate->update(['file_path' => $pdfPath]);

        return $certificate;
    }

    protected function generateCertificateNumber()
    {
        $year = date('Y');
        $random = strtoupper(Str::random(6));
        return "CERT-{$year}-{$random}";
    }

    protected function renderAndSavePdf(Certificate $certificate, CertificateTemplate $template, ExamAttempt $attempt)
    {
        $student = $attempt->student;
        $user = $student->user;
        $exam = $attempt->exam;

        // Prefer a FRONTEND_URL environment variable (e.g. http://localhost:5173) for user-facing links.
        $frontendBase = env('FRONTEND_URL', config('app.url'));
        $verificationUrl = rtrim($frontendBase, '/') . "/verify-certificate/{$certificate->verification_code}";

        // Generate QR image (base64). Prefer local package if available, otherwise fall back to external API.
        $qrImage = null;
        try {
            if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->margin(1)->generate($verificationUrl);
                $qrImage = base64_encode($qrPng);
            } else {
                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($verificationUrl);
                $qrPng = @file_get_contents($qrUrl);
                if ($qrPng !== false) {
                    $qrImage = base64_encode($qrPng);
                }
            }
        } catch (\Throwable $e) {
            $qrImage = null;
        }

        // Resolve logo path for DomPDF (must be a local filesystem path, not a URL)
        $logoPath = null;
        // Check Laravel's public directory for the logo file
        $publicLogo = public_path('my-logo.png');
        if (file_exists($publicLogo)) {
            $logoPath = $publicLogo;
        }

        // Build skills data array
        $skillsData = [];
        $skillRecords = $attempt->attemptSkills()->with('skill')->get();
        foreach ($skillRecords as $s) {
            $skillsData[] = [
                'name' => $s->skill->name ?? '',
                'points' => round(($s->score / 100) * 900),
                'score' => $s->score,
                'cefr' => $this->mapToCefr($s->score),
                'actfl' => $this->mapToActfl($s->score),
                'date' => $s->finished_at ? $s->finished_at->format('d M. Y') : now()->format('d M. Y'),
            ];
        }

        $overallScore = $attempt->overall_score ?? 0;
        $totalPoints = round(($overallScore / 100) * 900);
        $issueDate = $certificate->issue_date->format('M d, Y');

        // Render the dedicated PDF Blade view that mirrors the verify-certificate page
        $fullHtml = view('certificates.certificate_pdf', [
            'studentName' => $user->first_name . ' ' . $user->last_name,
            'score' => $overallScore,
            'totalPoints' => $totalPoints,
            'cefr' => $this->mapToCefr($overallScore),
            'actfl' => $this->mapToActfl($overallScore),
            'issueDate' => $issueDate,
            'certNumber' => $certificate->certificate_number,
            'skills' => $skillsData,
            'qrImage' => $qrImage,
            'logoPath' => $logoPath,
        ])->render();

        try {
            Log::info('CertificateService: rendering PDF with certificate_pdf blade', [
                'certificate_id' => $certificate->id,
                'student' => $user->first_name . ' ' . $user->last_name,
            ]);
        } catch (\Throwable $e) {
        }

        $pdf = Pdf::loadHTML($fullHtml)->setPaper('a4', 'landscape');

        $fileName = "certificates/{$certificate->certificate_number}.pdf";

        if (!Storage::disk('public')->exists('certificates')) {
            Storage::disk('public')->makeDirectory('certificates');
        }

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }

    public function wrapHtml($content, $template)
    {
        $backgroundPath = '';
        if ($template->background_image) {
            $backgroundPath = storage_path('app/public/' . $template->background_image);
        }
        $bgTag = '';
        if ($backgroundPath) {
            $bgTag = "<img class='bg-image' src='{$backgroundPath}' alt='background' />";
        }

        return "
        <html>
        <head>
            <meta http-equiv='Content-Type' content='text/html; charset=utf-8'/>
            <style>
                @page { size: A4 landscape; margin: 0; }
                body { 
                    font-family: 'DejaVu Sans', sans-serif; 
                    margin: 0; 
                    padding: 0;
                    width: 100%;
                    height: 100%;
                    position: relative;
                }
                .bg-image {
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    transform: translate(-50%, -50%);
                    width: 60%;
                    height: auto;
                    opacity: 0.04;
                    object-fit: contain;
                    z-index: 0;
                    pointer-events: none;
                }
                .content {
                    position: relative;
                    z-index: 1;
                    width: 1123px;
                    height: 794px;
                    margin: 0 auto;
                    box-sizing: border-box;
                    overflow: hidden;
                    page-break-inside: avoid;
                }
                /* Force signatures to bottom of page to avoid being pushed to next page */
                .content .signatures {
                    position: absolute;
                    bottom: 60px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 90%;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    z-index: 2;
                }
                .content table.scores {
                    margin-top: 40px;
                    page-break-inside: avoid;
                }
            </style>
        </head>
        <body>
            {$bgTag}
            <div class='content'>{$content}</div>
        </body>
        </html>
        ";
    }

    protected function generateSkillsTable($attempt)
    {
        if (!$attempt)
            return '';

        $rows = '';
        $skills = $attempt->attemptSkills()->with('skill')->get();

        foreach ($skills as $s) {
            $points = round(($s->score / 100) * 900);
            $date = $s->finished_at ? $s->finished_at->format('d M. Y') : now()->format('d M. Y');
            $rows .= "<tr>
                <td style='padding:8px;border:1px solid #444;text-align:left;'>Section: " . htmlspecialchars(ucfirst($s->skill->name)) . "</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$points}/900</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>" . number_format($s->score, 1) . "%</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$this->mapToCefr($s->score)}</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$this->mapToActfl($s->score)}</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$date}</td>
            </tr>";
        }

        // Overall row
        $overallScore = $attempt->overall_score ?? 0;
        $overallPoints = round(($overallScore / 100) * 900);
        $issueDate = $attempt->issue_date_formatted ?? now()->format('M d, Y');
        $rows .= "<tr style='font-weight:bold; background:#f1f5f9;'>
            <td style='padding:8px;border:1px solid #444;text-align:left;'>Overall Score</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>{$overallPoints}/900</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>" . number_format($overallScore, 1) . "%</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>{$this->mapToCefr($overallScore)}</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>{$this->mapToActfl($overallScore)}</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>{$issueDate}</td>
        </tr>";

        // Wrap rows in a complete table so injected HTML is valid
        $table = "<table class='scores-table' style='width:100%;margin-top:25px;border-collapse:collapse;font-size:11px;'>
            <thead>
                <tr>
                    <th style='padding:8px;border:1px solid #444;background:#f8fafc;font-weight:900;text-transform:uppercase;'>Test</th>
                    <th style='padding:8px;border:1px solid #444;background:#f8fafc;font-weight:900;text-transform:uppercase;'>Score</th>
                    <th style='padding:8px;border:1px solid #444;background:#f8fafc;font-weight:900;text-transform:uppercase;'>Score%</th>
                    <th style='padding:8px;border:1px solid #444;background:#f8fafc;font-weight:900;text-transform:uppercase;'>Level (CEFR)</th>
                    <th style='padding:8px;border:1px solid #444;background:#f8fafc;font-weight:900;text-transform:uppercase;'>Level (ACTFL)</th>
                    <th style='padding:8px;border:1px solid #444;background:#f8fafc;font-weight:900;text-transform:uppercase;'>Date</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>";

        return $table;
    }

    public function mapToCefr($score)
    {
        $points = round(($score / 100) * 900);

        if ($points >= 801)
            return 'C1.2';
        if ($points >= 701)
            return 'C1.1';
        if ($points >= 668)
            return 'B2.2';
        if ($points >= 634)
            return 'B2.1';
        if ($points >= 601)
            return 'B1.2';
        if ($points >= 501)
            return 'B1.1';
        if ($points >= 401)
            return 'A2.2';
        if ($points >= 301)
            return 'A2.1';
        if ($points >= 201)
            return 'A1.2';
        return 'A1.1';
    }

    public function mapToActfl($score)
    {
        $points = round(($score / 100) * 900);

        if ($points >= 801)
            return 'Superior';
        if ($points >= 701)
            return 'Advanced High';
        if ($points >= 668)
            return 'Advanced Mid+';
        if ($points >= 634)
            return 'Advanced Mid';
        if ($points >= 601)
            return 'Advanced Low';
        if ($points >= 501)
            return 'Intermediate High';
        if ($points >= 401)
            return 'Intermediate Mid';
        if ($points >= 301)
            return 'Intermediate Low';
        if ($points >= 201)
            return 'Novice High';
        if ($points >= 101)
            return 'Novice Mid';
        return 'Novice Low';
    }
}
