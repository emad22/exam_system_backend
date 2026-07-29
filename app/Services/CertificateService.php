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
                $qrPng = QrCode::format('png')->size(300)->margin(1)->generate($verificationUrl);
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
        $skillRecords = $attempt->attemptSkills()->with(['skill.levels'])->get();
        foreach ($skillRecords as $s) {
            $maxPoints  = $this->getSkillMaxPoints($s->skill, $s);
            $isCore     = $this->isCoreSkill($s);
            $skillType  = $this->getSkillType($s->skill->name ?? '');
            $skillsData[] = [
                'name'       => $this->normalizeSkillName($s->skill->name ?? ''),
                'max_points' => $maxPoints,
                'points'     => round(($s->score / 100) * $maxPoints),
                'score'      => $s->score,
                'cefr'       => $this->mapToCefr($s->score, $skillType),
                'actfl'      => $this->mapToActfl($s->score, $skillType),
                'date'       => $s->finished_at ? $s->finished_at->format('d M. Y') : now()->format('d M. Y'),
                'is_core'    => $isCore,
            ];
        }

        // Sort: core skills first (in display order), then extra skills
        $skillOrder = ['listening', 'reading', 'structure', 'writing', 'speaking'];
        usort($skillsData, function ($a, $b) use ($skillOrder) {
            // Core before non-core
            if ($a['is_core'] !== $b['is_core']) {
                return $a['is_core'] ? -1 : 1;
            }
            $aIndex = array_search(strtolower($a['name']), $skillOrder);
            $bIndex = array_search(strtolower($b['name']), $skillOrder);
            $aIndex = $aIndex === false ? 999 : $aIndex;
            $bIndex = $bIndex === false ? 999 : $bIndex;
            return $aIndex - $bIndex;
        });

        $overallScore = $attempt->overall_score ?? 0;
        // Overall points/max are computed from core skills only
        $coreSkills     = array_filter($skillsData, fn($s) => $s['is_core']);
        $totalPoints    = array_sum(array_column($coreSkills, 'points'));
        $totalMaxPoints = array_sum(array_column($coreSkills, 'max_points'));
        // Normalize to /900 scale: e.g. 1600/2700 → 533.33/900
        $overallNormalized900 = $totalMaxPoints > 0 ? round(($totalPoints / $totalMaxPoints) * 900, 2) : 0;
        $issueDate = $certificate->issue_date->format('M d, Y');

        if ($template && !empty($template->content_html)) {
            // 1. Build skills table rows HTML to replace {skills_table}
            $skillsHtml = '';
            // Core skills first
            foreach ($skillsData as $s) {
                if (!$s['is_core']) continue;
                $skillsHtml .= "<tr>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:left;'>Section: " . htmlspecialchars(ucfirst($s['name'])) . "</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>" . number_format((float) ($s['score'] ?? 0.0), 1) . "%</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['cefr']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['actfl']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['date']}</td>
                </tr>";
            }
            // Overall row (core only)
            $skillsHtml .= "<tr style='font-weight:bold; background:#f1f5f9;'>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:left;'>Overall Score (Sections Listening, Reading &amp; Structure)</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$overallNormalized900}/900</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>" . number_format((float) ($overallScore ?? 0.0), 1) . "%</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$this->mapToCefr($overallScore)}</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$this->mapToActfl($overallScore)}</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$issueDate}</td>
            </tr>";
            // Extra skills (Writing, Speaking, etc.) after overall
            foreach ($skillsData as $s) {
                if ($s['is_core']) continue;
                $skillsHtml .= "<tr>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:left;'>Section: " . htmlspecialchars(ucfirst($s['name'])) . "</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>" . number_format((float) ($s['score'] ?? 0.0), 1) . "%</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['cefr']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['actfl']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['date']}</td>
                </tr>";
            }

            // If {skills_table} is not wrapped inside a <table>/<tbody> in the template content, wrap it
            $hasTableWrapper = str_contains($template->content_html, '<tbody>{skills_table}')
                || str_contains($template->content_html, '<table>{skills_table}')
                || str_contains($template->content_html, '<thead>');

            if (!$hasTableWrapper) {
                $skillsHtml = "<table style='width:100%; border-collapse:collapse; font-size:12px;'>
                    <thead>
                        <tr style='background:#f8fafc;'>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>TEST</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>SCORE</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>SCORE%</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>LEVEL (CEFR)</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>LEVEL (ACTFL)</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>DATE</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$skillsHtml}
                    </tbody>
                </table>";
            }

            // 1b. Build skills table without CEFR (ACTFL only) HTML to replace {skills_table_without_cefr}
            $skillsNoCefrHtml = '';
            // Core skills first
            foreach ($skillsData as $s) {
                if (!$s['is_core']) continue;
                $skillsNoCefrHtml .= "<tr>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:left;'>Section: " . htmlspecialchars(ucfirst($s['name'])) . "</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>" . number_format((float) ($s['score'] ?? 0.0), 1) . "%</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['actfl']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['date']}</td>
                </tr>";
            }
            // Overall row (core only)
            $skillsNoCefrHtml .= "<tr style='font-weight:bold; background:#f1f5f9;'>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:left;'>Overall Score (Sections Listening, Reading &amp; Structure)</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$overallNormalized900}/900</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>" . number_format((float) ($overallScore ?? 0.0), 1) . "%</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$this->mapToActfl($overallScore)}</td>
                <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$issueDate}</td>
            </tr>";
            // Extra skills after overall
            foreach ($skillsData as $s) {
                if ($s['is_core']) continue;
                $skillsNoCefrHtml .= "<tr>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:left;'>Section: " . htmlspecialchars(ucfirst($s['name'])) . "</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>" . number_format((float) ($s['score'] ?? 0.0), 1) . "%</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['actfl']}</td>
                    <td style='border:1px solid #cbd5e1; padding:6px; text-align:center;'>{$s['date']}</td>
                </tr>";
            }

            $hasTableWrapperNoCefr = str_contains($template->content_html, '<tbody>{skills_table_without_cefr}')
                || str_contains($template->content_html, '<table>{skills_table_without_cefr}')
                || str_contains($template->content_html, '<thead>');

            if (!$hasTableWrapperNoCefr) {
                $skillsNoCefrHtml = "<table style='width:100%; border-collapse:collapse; font-size:12px;'>
                    <thead>
                        <tr style='background:#f8fafc;'>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>TEST</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>SCORE</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>SCORE%</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>LEVEL (ACTFL)</th>
                            <th style='border:1px solid #cbd5e1; padding:6px;'>DATE</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$skillsNoCefrHtml}
                    </tbody>
                </table>";
            }

            // 2. Build QR image HTML if present
            $qrHtml = '';
            if ($qrImage) {
                $qrHtml = "<img src=\"data:image/png;base64,{$qrImage}\" style=\"width:100%; height:100%; object-fit:contain;\" />";
            }

            // 3. Define and map all placeholders (including legacy seeder structure keys)
            $placeholders = [
                '{name}' => htmlspecialchars($user->first_name . ' ' . $user->last_name),
                '{exam}' => htmlspecialchars($exam->title ?? $exam->name ?? ''),
                '{score}' => number_format((float) ($overallScore ?? 0.0), 1),
                '{total_points}' => $overallNormalized900 . '/900',
                '{cefr}' => $this->mapToCefr($overallScore),
                '{actfl}' => $this->mapToActfl($overallScore),
                '{date}' => $issueDate,
                '{number}' => $certificate->certificate_number,
                '{verification_url}' => $verificationUrl,
                '{qr_code}' => $qrHtml,
                '{skills_table}' => $skillsHtml,
                '{skills_table_without_cefr}' => $skillsNoCefrHtml,
                // Legacy seeder fallbacks
                '{certificate_number}' => $certificate->certificate_number,
                '{issue_date}' => $issueDate,
                '{signer_left_name}' => 'Sayed Ramadan',
                '{signer_left_title}' => 'Program Director',
                '{org_address_line1}' => '3 alif Al-Nabataat Street,',
                '{org_address_line2}' => 'Garden City, Cairo, Egypt',
                '{signer_right_name}' => 'Hanan Dawah',
                '{signer_right_title}' => 'Registrar',
            ];

            $filledHtml = strtr($template->content_html, $placeholders);

            // 4. Wrap template inside A4 Landscape layout with styling and background
            $fullHtml = $this->wrapVisualTemplateHtml($filledHtml, $template);

            try {
                Log::info('CertificateService: rendering PDF with database visual template', [
                    'certificate_id' => $certificate->id,
                    'template_id' => $template->id,
                    'student' => $user->first_name . ' ' . $user->last_name,
                ]);
            } catch (\Throwable $e) {
            }
        } else {
            // Render the dedicated PDF Blade view that mirrors the verify-certificate page (Fallback)
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
                Log::info('CertificateService: rendering PDF with certificate_pdf blade (fallback)', [
                    'certificate_id' => $certificate->id,
                    'student' => $user->first_name . ' ' . $user->last_name,
                ]);
            } catch (\Throwable $e) {
            }
        }

        $pdf = Pdf::loadHTML($fullHtml)->setPaper('a4', 'landscape');

        $fileName = "certificates/{$certificate->certificate_number}.pdf";

        if (!Storage::disk('public')->exists('certificates')) {
            Storage::disk('public')->makeDirectory('certificates');
        }

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }

    /**
     * Render the certificate template HTML for the public verify page.
     * Returns the filled content_html (with background image inlined as base64)
     * wrapped in a basic HTML document, or null if no visual template is available.
     */
    public function renderForVerifyPage(Certificate $certificate): ?string
    {
        $attempt = $certificate->attempt;

        $template = null;
        if ($certificate->template_id) {
            $template = CertificateTemplate::find($certificate->template_id);
        }
        if (!$template) {
            $template = CertificateTemplate::where('is_default', true)->first()
                ?? CertificateTemplate::first();
        }

        if (!$template || empty($template->content_html)) {
            return null;
        }

        $student = $attempt->student;
        $user    = $student->user;
        $exam    = $attempt->exam;

        $frontendBase    = env('FRONTEND_URL', config('app.url'));
        $verificationUrl = rtrim($frontendBase, '/') . "/verify-certificate/{$certificate->verification_code}";

        // QR code (base64)
        $qrImage = null;
        try {
            if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                $qrPng   = QrCode::format('png')->size(300)->margin(1)->generate($verificationUrl);
                $qrImage = base64_encode($qrPng);
            } else {
                $qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($verificationUrl);
                $qrPng   = @file_get_contents($qrUrl);
                if ($qrPng !== false) {
                    $qrImage = base64_encode($qrPng);
                }
            }
        } catch (\Throwable $e) {
            $qrImage = null;
        }

        // Skills data
        $skillsData   = [];
        $skillRecords = $attempt->attemptSkills()->with(['skill.levels'])->get();
        foreach ($skillRecords as $s) {
            $maxPoints = $this->getSkillMaxPoints($s->skill, $s);
            $isCore     = $this->isCoreSkill($s);
            $skillType  = $this->getSkillType($s->skill->name ?? '');
            $skillsData[] = [
                'name'       => $this->normalizeSkillName($s->skill->name ?? ''),
                'max_points' => $maxPoints,
                'points'     => round(($s->score / 100) * $maxPoints),
                'score'      => $s->score,
                'cefr'       => $this->mapToCefr($s->score, $skillType),
                'actfl'      => $this->mapToActfl($s->score, $skillType),
                'date'       => $s->finished_at ? $s->finished_at->format('d M. Y') : now()->format('d M. Y'),
                'is_core'    => $isCore,
            ];
        }

        // Sort: core skills first, then extra
        $skillOrder = ['listening', 'reading', 'structure', 'writing', 'speaking'];
        usort($skillsData, function ($a, $b) use ($skillOrder) {
            if ($a['is_core'] !== $b['is_core']) {
                return $a['is_core'] ? -1 : 1;
            }
            $aIndex = array_search(strtolower($a['name']), $skillOrder);
            $bIndex = array_search(strtolower($b['name']), $skillOrder);
            $aIndex = $aIndex === false ? 999 : $aIndex;
            $bIndex = $bIndex === false ? 999 : $bIndex;
            return $aIndex - $bIndex;
        });

        $overallScore = $attempt->overall_score ?? 0;
        // Overall computed from core skills only
        $coreSkills     = array_filter($skillsData, fn($s) => $s['is_core']);
        $totalPoints    = array_sum(array_column($coreSkills, 'points'));
        $totalMaxPoints = array_sum(array_column($coreSkills, 'max_points'));
        // Normalize to /900 scale: e.g. 1600/2700 → 533.33/900
        $overallNormalized900 = $totalMaxPoints > 0 ? round(($totalPoints / $totalMaxPoints) * 900, 2) : 0;
        $issueDate    = $certificate->issue_date->format('M d, Y');

        // Skills table HTML: core → overall → extra
        $skillsHtml = '';
        foreach ($skillsData as $s) {
            if (!$s['is_core']) continue;
            $skillsHtml .= "<tr>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:left;'>Section: " . htmlspecialchars(ucfirst($s['name'])) . "</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>" . number_format((float)($s['score'] ?? 0), 1) . "%</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['cefr']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['actfl']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['date']}</td>
            </tr>";
        }
        // Overall row (core skills only)
        $skillsHtml .= "<tr style='font-weight:bold;background:#f1f5f9;'>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:left;'>Overall Score (Sections Listening, Reading &amp; Structure)</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$overallNormalized900}/900</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>" . number_format((float)($overallScore ?? 0), 1) . "%</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$this->mapToCefr($overallScore)}</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$this->mapToActfl($overallScore)}</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$issueDate}</td>
        </tr>";
        // Extra skills (Writing, Speaking…) after overall
        foreach ($skillsData as $s) {
            if ($s['is_core']) continue;
            $skillsHtml .= "<tr>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:left;'>Section: " . htmlspecialchars(ucfirst($s['name'])) . "</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>" . number_format((float)($s['score'] ?? 0), 1) . "%</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['cefr']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['actfl']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['date']}</td>
            </tr>";
        }

        // Only wrap with <table><thead> if the template doesn't already contain a header for skills_table
        $hasTableWrapper = str_contains($template->content_html, '<tbody>{skills_table}')
            || str_contains($template->content_html, '<table>{skills_table}')
            || str_contains($template->content_html, '<thead>');

        if (!$hasTableWrapper) {
            $skillsHtml = "<table style='width:100%;border-collapse:collapse;font-size:12px;'>
                <thead><tr style='background:#f8fafc;'>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>TEST</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>SCORE</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>SCORE%</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>LEVEL (CEFR)</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>LEVEL (ACTFL)</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>DATE</th>
                </tr></thead>
                <tbody>{$skillsHtml}</tbody>
            </table>";
        } else {
            // Template already has its own <thead>, just wrap rows in tbody
            $skillsHtml = "<tbody>{$skillsHtml}</tbody>";
        }

        // Skills table without CEFR: core → overall → extra
        $skillsNoCefrHtml = '';
        foreach ($skillsData as $s) {
            if (!$s['is_core']) continue;
            $skillsNoCefrHtml .= "<tr>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:left;'>Section: " . htmlspecialchars(ucfirst($s['name'])) . "</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>" . number_format((float)($s['score'] ?? 0), 1) . "%</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['actfl']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['date']}</td>
            </tr>";
        }
        // Overall row (core only)
        $skillsNoCefrHtml .= "<tr style='font-weight:bold;background:#f1f5f9;'>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:left;'>Overall Score (Sections Listening, Reading &amp; Structure)</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$overallNormalized900}/900</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>" . number_format((float)($overallScore ?? 0), 1) . "%</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$this->mapToActfl($overallScore)}</td>
            <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$issueDate}</td>
        </tr>";
        // Extra skills after overall
        foreach ($skillsData as $s) {
            if ($s['is_core']) continue;
            $skillsNoCefrHtml .= "<tr>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:left;'>Section: " . htmlspecialchars(ucfirst($s['name'])) . "</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>" . number_format((float)($s['score'] ?? 0), 1) . "%</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['actfl']}</td>
                <td style='border:1px solid #cbd5e1;padding:6px;text-align:center;'>{$s['date']}</td>
            </tr>";
        }

        $hasTableWrapperNoCefr = str_contains($template->content_html, '<tbody>{skills_table_without_cefr}')
            || str_contains($template->content_html, '<table>{skills_table_without_cefr}')
            || str_contains($template->content_html, '<thead>');

        if (!$hasTableWrapperNoCefr) {
            $skillsNoCefrHtml = "<table style='width:100%;border-collapse:collapse;font-size:12px;'>
                <thead><tr style='background:#f8fafc;'>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>TEST</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>SCORE</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>SCORE%</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>LEVEL (ACTFL)</th>
                    <th style='border:1px solid #cbd5e1;padding:6px;'>DATE</th>
                </tr></thead>
                <tbody>{$skillsNoCefrHtml}</tbody>
            </table>";
        } else {
            // Template already has its own <thead>, just wrap rows in tbody
            $skillsNoCefrHtml = "<tbody>{$skillsNoCefrHtml}</tbody>";
        }

        $qrHtml = $qrImage
            ? "<img src=\"data:image/png;base64,{$qrImage}\" style=\"width:100%;height:100%;object-fit:contain;\" />"
            : '';

        $placeholders = [
            '{name}'                   => htmlspecialchars($user->first_name . ' ' . $user->last_name),
            '{exam}'                   => htmlspecialchars($exam->title ?? $exam->name ?? ''),
            '{score}'                  => number_format((float)($overallScore ?? 0), 1),
            '{total_points}'           => $overallNormalized900 . '/900',
            '{cefr}'                   => $this->mapToCefr($overallScore),
            '{actfl}'                  => $this->mapToActfl($overallScore),
            '{date}'                   => $issueDate,
            '{number}'                 => $certificate->certificate_number,
            '{verification_url}'       => $verificationUrl,
            '{qr_code}'                => $qrHtml,
            '{skills_table}'           => $skillsHtml,
            '{skills_table_without_cefr}' => $skillsNoCefrHtml,
            '{certificate_number}'     => $certificate->certificate_number,
            '{issue_date}'             => $issueDate,
            '{signer_left_name}'       => 'Sayed Ramadan',
            '{signer_left_title}'      => 'Program Director',
            '{org_address_line1}'      => '3 alif Al-Nabataat Street,',
            '{org_address_line2}'      => 'Garden City, Cairo, Egypt',
            '{signer_right_name}'      => 'Hanan Dawah',
            '{signer_right_title}'     => 'Registrar',
        ];

        $filledHtml = strtr($template->content_html, $placeholders);

        // Build background CSS for the browser (use a URL instead of base64 to keep response size manageable)
        $bgCss = '';
        if ($template->background_image) {
            $backendBase = env('APP_URL', '');
            $bgUrl = rtrim($backendBase, '/') . '/storage/' . ltrim($template->background_image, '/');

            $settings  = [];
            if (!empty($template->background_settings)) {
                $settings = is_array($template->background_settings)
                    ? $template->background_settings
                    : (json_decode($template->background_settings, true) ?: []);
            }
            $opacity  = isset($settings['opacity'])  ? floatval($settings['opacity'])            : 1.0;
            $size     = isset($settings['size'])     ? htmlspecialchars($settings['size'])        : 'cover';
            $position = isset($settings['position']) ? htmlspecialchars($settings['position'])    : 'center';

            $bgCss = "
                .cert-bg-layer {
                    position: absolute; left: 0; top: 0; width: 100%; height: 100%;
                    z-index: 0; pointer-events: none;
                    background-image: url('{$bgUrl}');
                    background-size: {$size};
                    background-position: {$position};
                    background-repeat: no-repeat;
                    opacity: {$opacity};
                }
            ";
        }

        return "<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'/>
<style>
    html, body { margin:0; padding:0; background:transparent; font-family: Arial, sans-serif; }
    .cert-bg-layer { display: none; }
    {$bgCss}
    .cert-bg-layer { display: block; }
    * { box-sizing: border-box; }
</style>
</head>
<body>
    <div style='position:relative; width:1123px; height:794px; overflow:hidden;'>
        <div class='cert-bg-layer'></div>
        <div style='position:relative; z-index:1; width:1123px; height:794px;'>
            {$filledHtml}
        </div>
    </div>
</body>
</html>";
    }

    public function wrapVisualTemplateHtml($content, $template)
    {
        $backgroundPath = '';
        if ($template->background_image) {
            $backgroundPath = storage_path('app/public/' . $template->background_image);
        }

        $bgLayerHtml = '';
        if ($backgroundPath && file_exists($backgroundPath)) {
            // Parse background settings from JSON
            $settings = [];
            if (!empty($template->background_settings)) {
                if (is_array($template->background_settings)) {
                    $settings = $template->background_settings;
                } else {
                    $settings = json_decode($template->background_settings, true) ?: [];
                }
            }

            $opacity = isset($settings['opacity']) ? floatval($settings['opacity']) : 1.0;
            $size = isset($settings['size']) ? htmlspecialchars($settings['size']) : 'cover';
            $position = isset($settings['position']) ? htmlspecialchars($settings['position']) : 'center';
            $customCss = isset($settings['custom_css']) ? $settings['custom_css'] : '';

            // Base64 encode the image for reliable rendering in DomPDF
            $bgBase64 = '';
            try {
                $bgData = file_get_contents($backgroundPath);
                $bgType = pathinfo($backgroundPath, PATHINFO_EXTENSION);
                $bgBase64 = 'data:image/' . $bgType . ';base64,' . base64_encode($bgData);
            } catch (\Throwable $e) {
                $bgBase64 = $backgroundPath;
            }

            $bgLayerStyles = "position: absolute; left: 0; top: 0; width: 100%; height: 100%; z-index: -100; opacity: {$opacity}; background-image: url('{$bgBase64}'); background-size: {$size}; background-position: {$position}; background-repeat: no-repeat; {$customCss}";
            $bgLayerHtml = "<div style=\"{$bgLayerStyles}\"></div>";
        }

        $content = trim($content); // مهم
        return "
        <html>
        <head>
            <meta http-equiv='Content-Type' content='text/html; charset=utf-8'/>
            <style>
                @page { size: A4 landscape; margin: 0; }
                html, body {
                    margin: 0;
                    padding: 0;
                    width: 297mm;
                    height: 210mm;
                    overflow: hidden;
                    page-break-after: avoid;
                    page-break-inside: avoid;
                }
                body {
                    font-family: 'DejaVu Sans', Arial, sans-serif;
                    position: relative;
                }
                .page-wrapper {
                    width: 297mm;
                    height: 210mm;
                    margin: 0;
                    padding: 0;
                    overflow: hidden;
                    position: relative;
                    page-break-inside: avoid;
                    page-break-after: avoid;
                }
                .page-wrapper > div {
                    width: 1120px !important;
                    height: 790px !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    page-break-inside: avoid !important;
                    page-break-after: avoid !important;
                }
                * {
                    page-break-inside: avoid;
                    page-break-after: avoid;
                }
                table, tr, td, th {
                    page-break-inside: avoid !important;
                }
            </style>
        </head>
        <body>
            {$bgLayerHtml}
            <div class='page-wrapper'>{$content}</div>
        </body>
        </html>
        ";
    }

    public function wrapHtml($content, $template)
    {
        $backgroundPath = '';
        if ($template->background_image) {
            $backgroundPath = storage_path('app/public/' . $template->background_image);
        }
        $bgStyle = '';
        if ($backgroundPath && file_exists($backgroundPath)) {
            // Use explicit A4 landscape dimensions for background to avoid overflow
            $bgStyle = "background-image: url('{$backgroundPath}'); background-size: 297mm 210mm; background-position: center center; background-repeat: no-repeat; opacity: 0.04;";
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
                    overflow: hidden;
                }
                .content {
                    position: relative;
                    z-index: 1;
                    /* A4 landscape exact size to match @page */
                    width: 297mm;
                    height: 210mm;
                    margin: 0 auto;
                    box-sizing: border-box;
                    overflow: hidden;
                    page-break-inside: avoid;
                    {$bgStyle}
                    opacity: 1;
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
            <div class='content'>{$content}</div>
        </body>
        </html>
        ";
    }

    protected function generateSkillsTable($attempt)
    {
        if (!$attempt)
            return '';

        $skillRecords = $attempt->attemptSkills()->with(['skill.levels'])->get();

        // Build a plain array with all computed values
        $skillOrder = ['listening', 'reading', 'structure', 'writing', 'speaking'];
        $skills = [];
        foreach ($skillRecords as $s) {
            $maxPoints   = $this->getSkillMaxPoints($s->skill, $s);
            $isCore      = $this->isCoreSkill($s);
            $name        = strtolower($this->normalizeSkillName($s->skill->name ?? ''));
            $skillType   = $this->getSkillType($s->skill->name ?? '');
            $skills[] = [
                'name'       => $this->normalizeSkillName($s->skill->name ?? ''),
                'name_lower' => $name,
                'max_points' => $maxPoints,
                'points'     => round(($s->score / 100) * $maxPoints),
                'score'      => $s->score,
                'cefr'       => $this->mapToCefr($s->score, $skillType),
                'actfl'      => $this->mapToActfl($s->score, $skillType),
                'date'       => $s->finished_at ? $s->finished_at->format('d M. Y') : now()->format('d M. Y'),
                'is_core'    => $isCore,
            ];
        }

        // Sort: core first, then extra; within each group follow $skillOrder
        usort($skills, function ($a, $b) use ($skillOrder) {
            if ($a['is_core'] !== $b['is_core']) {
                return $a['is_core'] ? -1 : 1;
            }
            $aIndex = array_search($a['name_lower'], $skillOrder);
            $bIndex = array_search($b['name_lower'], $skillOrder);
            $aIndex = $aIndex === false ? 999 : $aIndex;
            $bIndex = $bIndex === false ? 999 : $bIndex;
            return $aIndex - $bIndex;
        });

        $overallScore   = $attempt->overall_score ?? 0;
        $coreSkills     = array_filter($skills, fn($s) => $s['is_core']);
        $overallPoints    = array_sum(array_column($coreSkills, 'points'));
        $overallMaxPoints = array_sum(array_column($coreSkills, 'max_points'));
        // Normalize to /900 scale: e.g. 1600/2700 → 533.33/900
        $overallNormalized900 = $overallMaxPoints > 0 ? round(($overallPoints / $overallMaxPoints) * 900, 2) : 0;
        $issueDate = now()->format('M d, Y');

        $rows = '';

        // Core skill rows
        foreach ($skills as $s) {
            if (!$s['is_core']) continue;
            $rows .= "<tr>
                <td style='padding:8px;border:1px solid #444;text-align:left;'>Section: " . htmlspecialchars($s['name']) . "</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>" . number_format($s['score'], 1) . "%</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$s['cefr']}</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$s['actfl']}</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$s['date']}</td>
            </tr>";
        }

        // Overall row (core skills only)
        $rows .= "<tr style='font-weight:bold; background:#f1f5f9;'>
            <td style='padding:8px;border:1px solid #444;text-align:left;'>Overall Score (Sections Listening, Reading &amp; Structure)</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>{$overallNormalized900}/900</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>" . number_format($overallScore, 1) . "%</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>{$this->mapToCefr($overallScore)}</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>{$this->mapToActfl($overallScore)}</td>
            <td style='padding:8px;border:1px solid #444;text-align:center;'>{$issueDate}</td>
        </tr>";

        // Extra skill rows (Writing, Speaking…) after overall
        foreach ($skills as $s) {
            if ($s['is_core']) continue;
            $rows .= "<tr>
                <td style='padding:8px;border:1px solid #444;text-align:left;'>Section: " . htmlspecialchars($s['name']) . "</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$s['points']}/{$s['max_points']}</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>" . number_format($s['score'], 1) . "%</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$s['cefr']}</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$s['actfl']}</td>
                <td style='padding:8px;border:1px solid #444;text-align:center;'>{$s['date']}</td>
            </tr>";
        }

        return "<table class='scores-table' style='width:100%;margin-top:25px;border-collapse:collapse;font-size:11px;'>
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
    }

    /**
     * Check if a skill is core (participates in overall score).
     * Core skills are those WITHOUT a custom max_points in exam_skill pivot,
     * meaning they follow level-based scoring (9 levels × 100 = 900).
     */
    protected function isCoreSkill($attemptSkill): bool
    {
        if (!$attemptSkill) {
            return true; // fallback
        }

        try {
            $examSkillRow = \App\Models\ExamSkill::where('exam_id', $attemptSkill->attempt->exam_id)
                ->where('skill_id', $attemptSkill->skill_id)
                ->first();

            // If max_points is set and > 0 → extra skill (not core)
            return !($examSkillRow && $examSkillRow->max_points > 0);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Calculate the maximum points for a skill.
     *
     * Priority:
     *  1. exam_skill.max_points  – a custom value set per exam (e.g. Speaking = 900 even if it has 1 level)
     *  2. level count × 100      – dynamic: 9 levels → 900, 6 levels → 600, etc.
     *  3. Hard fallback           – 900
     *
     * @param  \App\Models\Skill|null          $skill
     * @param  \App\Models\ExamAttemptSkill|null $attemptSkill  pass the pivot record to read exam_skill.max_points
     */
    protected function getSkillMaxPoints($skill, $attemptSkill = null): int
    {
        // 1. Check exam_skill pivot max_points via the attempt's exam
        if ($attemptSkill) {
            try {
                $examSkillRow = \App\Models\ExamSkill::where('exam_id', $attemptSkill->attempt->exam_id)
                    ->where('skill_id', $attemptSkill->skill_id)
                    ->first();

                if ($examSkillRow && $examSkillRow->max_points > 0) {
                    return (int) $examSkillRow->max_points;
                }
            } catch (\Throwable $e) {
                // fall through to level-count logic
            }
        }

        if (!$skill) {
            return 900;
        }

        // 2. Level count × 100
        $levelCount = $skill->relationLoaded('levels')
            ? $skill->levels->count()
            : $skill->levels()->count();

        return $levelCount > 0 ? $levelCount * 100 : 900;
    }

    /**
     * Normalize skill name for display and sorting.
     * "live speaking", "Live Speaking", "speaking" → "Speaking"
     */
    protected function normalizeSkillName(string $name): string
    {
        $lower = strtolower(trim($name));
        if (str_contains($lower, 'speaking')) return 'Speaking';
        if (str_contains($lower, 'listening')) return 'Listening';
        if (str_contains($lower, 'reading'))   return 'Reading';
        if (str_contains($lower, 'writing') || str_contains($lower, 'writting')) return 'Writing';
        if (str_contains($lower, 'structure') || str_contains($lower, 'grammar')) return 'Structure';
        return ucfirst($name);
    }

    /**
     * Map a score percentage to a CEFR level.
     *
     * @param  float   $score  0–100 percentage
     * @param  string  $type   'core' (Listening/Reading/Structure) | 'productive' (Writing/Speaking)
     */
    public function mapToCefr(float $score, string $type = 'core'): string
    {
        return $this->mapLevel($score, $type, 'cefr');
    }

    /**
     * Map a score percentage to an ACTFL level.
     *
     * @param  float   $score  0–100 percentage
     * @param  string  $type   'core' | 'productive'
     */
    public function mapToActfl(float $score, string $type = 'core'): string
    {
        return $this->mapLevel($score, $type, 'actfl');
    }

    /**
     * Resolve the skill group type from a skill name.
     */
    protected function getSkillType(string $skillName): string
    {
        $lower = strtolower(trim($skillName));
        if (str_contains($lower, 'writing') || str_contains($lower, 'writting') || str_contains($lower, 'speaking')) {
            return 'productive';
        }
        return 'core';
    }

    /**
     * Core mapping logic.
     *
     * Lookup order:
     *   1. DB table `cefr_actfl_thresholds` (cached, admin-editable)
     *   2. Fallback to config/cefr_actfl.php (static defaults)
     *
     * - core:       converts score% → /900 points before comparison
     * - productive: uses score% directly
     */
    protected function mapLevel(float $score, string $type, string $framework): string
    {
        // 1. Try DB (cached for 60 min)
        try {
            $thresholds = \Illuminate\Support\Facades\Cache::remember(
                'cefr_actfl_thresholds',
                3600,
                fn () => \App\Models\CefrActflThreshold::active()
                    ->orderBy('skill_group')
                    ->orderBy('framework')
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy(['skill_group', 'framework'])
            );
        } catch (\Throwable $e) {
            $thresholds = [];
        }

        $rows = $thresholds[$type][$framework] ?? null;

        if ($rows && $rows->isNotEmpty()) {
            $value = ($type === 'core') ? round(($score / 100) * 900) : round($score);
            foreach ($rows as $row) {
                if ($value >= $row->min_score) {
                    return $row->level_label;
                }
            }
            return $rows->last()->level_label;
        }

        // 2. Fallback to config file
        $configThresholds = config("cefr_actfl.{$type}.{$framework}", []);

        if (empty($configThresholds)) {
            return 'N/A';
        }

        // $value = ($type === 'core') ? round(($score / 100) * 900) : round($score);
        $value = round(  ($score / 100 ) * 900  );

        foreach ($configThresholds as $minPoints => $label) {
            if ($value >= $minPoints) {
                return $label;
            }
        }

        return end($configThresholds) ?: 'N/A';
    }
}
