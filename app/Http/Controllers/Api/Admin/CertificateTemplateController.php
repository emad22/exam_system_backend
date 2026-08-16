<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CertificateTemplateController extends Controller
{
    public function index()
    {
        return response()->json(CertificateTemplate::latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $this->authorize('create', CertificateTemplate::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'content_html' => 'required|string',
            'background_image' => 'nullable|image|max:2048',
            'is_default' => 'sometimes|boolean',
            'elements_json' => 'nullable|string',
            'background_settings' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'content_html', 'is_default']);
        $data['elements_json'] = $request->input('elements_json');
        $data['background_settings'] = $request->input('background_settings');
        // XSS Protection - preserve data-URL images and inline styles when present
        if (isset($data['content_html'])) {
            if (stripos($data['content_html'], 'data:image') !== false) {
                // Keep as-is to preserve embedded images created in the visual editor
            } else {
                $data['content_html'] = $this->sanitizeHtml($data['content_html']);
            }
        }

        if ($request->hasFile('background_image')) {
            $data['background_image'] = $request->file('background_image')->store('templates', 'public');
        }

        return \DB::transaction(function () use ($data) {
            if ($data['is_default'] ?? false) {
                CertificateTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            $template = CertificateTemplate::create($data);
            return response()->json($template, 201);
        });
    }

    public function show(CertificateTemplate $certificate_template)
    {
        Log::info('CertificateTemplateController@show called', ['id' => $certificate_template->id, 'data' => $certificate_template->toArray()]);
        return response()->json($certificate_template);
    }

    public function update(Request $request, CertificateTemplate $certificate_template)
    {
        $this->authorize('update', $certificate_template);

        $request->validate([
            'name' => 'required|string|max:255',
            'content_html' => 'required|string',
            'background_image' => 'nullable|image|max:2048',
            'is_default' => 'sometimes|boolean',
            'elements_json' => 'nullable|string',
            'background_settings' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'content_html', 'is_default']);
        $data['elements_json'] = $request->input('elements_json');
        $data['background_settings'] = $request->input('background_settings');

        // XSS Protection - preserve data-URL images and inline styles when present
        if (isset($data['content_html'])) {
            if (stripos($data['content_html'], 'data:image') !== false) {
                // Keep as-is to preserve embedded images created in the visual editor
            } else {
                $data['content_html'] = $this->sanitizeHtml($data['content_html']);
            }
        }

        if ($request->hasFile('background_image')) {
            // Delete old image if exists
            if ($certificate_template->background_image) {
                Storage::disk('public')->delete($certificate_template->background_image);
            }
            $data['background_image'] = $request->file('background_image')->store('templates', 'public');
        }

        return \DB::transaction(function () use ($data, $certificate_template) {
            if ($data['is_default'] ?? false) {
                CertificateTemplate::where('id', '!=', $certificate_template->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $certificate_template->update($data);
            return response()->json($certificate_template->fresh());
        });
    }

    public function destroy(CertificateTemplate $certificate_template)
    {
        $this->authorize('delete', $certificate_template);

        if ($certificate_template->background_image) {
            Storage::disk('public')->delete($certificate_template->background_image);
        }
        $certificate_template->delete();
        return response()->json(['message' => 'Template deleted successfully']);
    }

    public function previewPdf(CertificateTemplate $certificate_template)
    {
        $this->authorize('view', $certificate_template);

        // Dummy data for preview
        $dateFormat = app(\App\Services\CertificateService::class)->getCertificateDateFormat($certificate_template);
        $placeholders = [
            '{name}' => 'Sample Student Name',
            '{date}' => app(\App\Services\CertificateService::class)->formatCertificateDate(now(), $dateFormat),
            '{score}' => '82.7',
            '{total_points}' => '745',
            '{cefr}' => 'C1.2',
            '{actfl}' => 'Advanced High',
            '{exam}' => 'Sample Exam Name',
            '{number}' => 'CERT-SAMPLE-001',
            '{verification_url}' => url("/verify-certificate/sample-code"),
            '{skills_table}' => '
                <tr><td style="border:1px solid #cbd5e1; padding:6px; text-align:left;">Section: Composition</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">810/900</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">90.0%</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">C2</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">Superior</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">25 Aug. 2022</td></tr>
                <tr><td style="border:1px solid #cbd5e1; padding:6px; text-align:left;">Section: Speaking</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">680/900</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">75.6%</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">C1.1</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">Advanced Mid +</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">25 Aug. 2022</td></tr>
            ',
            '{skills_table_without_cefr}' => '
                <tr><td style="border:1px solid #cbd5e1; padding:6px; text-align:left;">Section: Composition</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">810/900</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">90.0%</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">Superior</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">25 Aug. 2022</td></tr>
                <tr><td style="border:1px solid #cbd5e1; padding:6px; text-align:left;">Section: Speaking</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">680/900</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">75.6%</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">Advanced Mid +</td><td style="border:1px solid #cbd5e1; padding:6px; text-align:center;">25 Aug. 2022</td></tr>
            '
        ];

        $html = str_replace(array_keys($placeholders), array_values($placeholders), $certificate_template->content_html);

        $service = app(\App\Services\CertificateService::class);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($service->wrapHtml($html, $certificate_template))
            ->setPaper('a4', 'landscape');

        return $pdf->download("Template-Preview-{$certificate_template->id}.pdf");
    }

    protected function sanitizeHtml($html)
    {
        if (function_exists('clean')) {
            return clean($html);
        }

        // Fallback simple sanitizer if purifier isn't installed
        return strip_tags($html, '<h1><h2><h3><h4><h5><h6><p><br><strong><em><ul><li><ol><span><div><table><thead><tbody><tr><td><th><img><style>');
    }

}
