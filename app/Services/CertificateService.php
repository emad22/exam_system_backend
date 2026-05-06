<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ExamAttempt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CertificateService
{
    /**
     * Generate a certificate for a given exam attempt.
     */
    public function generate(ExamAttempt $attempt)
    {
        // 2. Get the template (default or first available)
        $template = CertificateTemplate::where('is_default', true)->first() 
                    ?? CertificateTemplate::first();

        if (!$template) {
            throw new \Exception("No certificate template found. Please create one in Admin panel.");
        }

        // 1. Check if certificate already exists
        $certificate = $attempt->certificate;
        
        if ($certificate) {
            // Update existing record to use current default template
            $certificate->update(['template_id' => $template->id]);
        } else {
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

        // Replace Placeholders in HTML
        $placeholders = [
            '{name}' => $user->first_name . ' ' . $user->last_name,
            '{date}' => $certificate->issue_date->format('M d, Y'),
            '{score}' => $certificate->score,
            '{total_points}' => round(($certificate->score / 100) * 900),
            '{cefr}' => $this->mapToCefr($certificate->score),
            '{actfl}' => $this->mapToActfl($certificate->score),
            '{exam}' => $exam->name,
            '{number}' => $certificate->certificate_number,
            '{verification_url}' => url("/verify-certificate/{$certificate->verification_code}"),
            '{skills_table}' => $this->generateSkillsTable($attempt)
        ];

        $html = str_replace(array_keys($placeholders), array_values($placeholders), $template->content_html);

        // Render PDF
        $pdf = Pdf::loadHTML($this->wrapInBaseStyle($html, $template))
                  ->setPaper('a4', 'landscape');

        $fileName = "certificates/{$certificate->certificate_number}.pdf";
        
        // Ensure directory exists
        if (!Storage::disk('public')->exists('certificates')) {
            Storage::disk('public')->makeDirectory('certificates');
        }

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }

    protected function wrapInBaseStyle($content, $template)
    {
        $backgroundPath = '';
        if ($template->background_image) {
            $backgroundPath = public_path('storage/' . $template->background_image);
        }
        
        return "
        <html>
        <head>
            <meta http-equiv='Content-Type' content='text/html; charset=utf-8'/>
            <style>
                @page { margin: 0; }
                body { 
                    font-family: 'DejaVu Sans', sans-serif; 
                    margin: 0; 
                    padding: 0;
                    background-image: url('{$backgroundPath}');
                    background-size: cover;
                    background-repeat: no-repeat;
                    width: 100%;
                    height: 100%;
                }
            </style>
        </head>
        <body>
            {$content}
        </body>
        </html>
        ";
    }

    protected function generateSkillsTable($attempt)
    {
        if (!$attempt) return '';
        
        $html = '';
        $skills = $attempt->attemptSkills()->with('skill')->get();
        
        foreach ($skills as $s) {
            $points = round(($s->score / 100) * 900);
            $cefr = $this->mapToCefr($s->score);
            $actfl = $this->mapToActfl($s->score);
            $date = $s->finished_at ? $s->finished_at->format('d M. Y') : now()->format('d M. Y');
            
            $html .= "<tr>
                <td>Section: " . ucfirst($s->skill->name) . "</td>
                <td>{$points}/900</td>
                <td>" . number_format($s->score, 1) . "%</td>
                <td>{$cefr}</td>
                <td>{$actfl}</td>
                <td>{$date}</td>
            </tr>";
        }
        
        return $html;
    }

    public function mapToCefr($score)
    {
        $points = round(($score / 100) * 900);

        if ($points >= 801) return 'C1.2';
        if ($points >= 701) return 'C1.1';
        if ($points >= 668) return 'B2.2';
        if ($points >= 634) return 'B2.1';
        if ($points >= 601) return 'B1.2';
        if ($points >= 501) return 'B1.1';
        if ($points >= 401) return 'A2.2';
        if ($points >= 301) return 'A2.1';
        if ($points >= 201) return 'A1.2';
        return 'A1.1';
    }

    public function mapToActfl($score)
    {
        $points = round(($score / 100) * 900);

        if ($points >= 801) return 'Superior';
        if ($points >= 701) return 'Advanced High';
        if ($points >= 668) return 'Advanced Mid+';
        if ($points >= 634) return 'Advanced Mid';
        if ($points >= 601) return 'Advanced Low';
        if ($points >= 501) return 'Intermediate High';
        if ($points >= 401) return 'Intermediate Mid';
        if ($points >= 301) return 'Intermediate Low';
        if ($points >= 201) return 'Novice High';
        if ($points >= 101) return 'Novice Mid';
        return 'Novice Low';
    }
}
