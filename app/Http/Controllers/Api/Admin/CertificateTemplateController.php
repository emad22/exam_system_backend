<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'is_default' => 'sometimes|boolean'
        ]);

        $data = $request->only(['name', 'content_html', 'is_default']);

        // XSS Protection
        if (isset($data['content_html'])) {
            $data['content_html'] = $this->sanitizeHtml($data['content_html']);
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

    public function show(CertificateTemplate $template)
    {
        return response()->json($template);
    }

    public function update(Request $request, CertificateTemplate $template)
    {
        $this->authorize('update', $template);

        $request->validate([
            'name' => 'required|string|max:255',
            'content_html' => 'required|string',
            'background_image' => 'nullable|image|max:2048',
            'is_default' => 'sometimes|boolean'
        ]);

        $data = $request->only(['name', 'content_html', 'is_default']);

        // XSS Protection
        if (isset($data['content_html'])) {
            $data['content_html'] = $this->sanitizeHtml($data['content_html']);
        }

        if ($request->hasFile('background_image')) {
            // Delete old image if exists
            if ($template->background_image) {
                Storage::disk('public')->delete($template->background_image);
            }
            $data['background_image'] = $request->file('background_image')->store('templates', 'public');
        }

        return \DB::transaction(function () use ($data, $template) {
            if ($data['is_default'] ?? false) {
                CertificateTemplate::where('id', '!=', $template->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $template->update($data);
            return response()->json($template);
        });
    }

    public function destroy(CertificateTemplate $template)
    {
        $this->authorize('delete', $template);

        if ($template->background_image) {
            Storage::disk('public')->delete($template->background_image);
        }
        $template->delete();
        return response()->json(['message' => 'Template deleted successfully']);
    }

    public function previewPdf(CertificateTemplate $template)
    {
        $this->authorize('view', $template);

        // Dummy data for preview
        $placeholders = [
            '{name}' => 'Sample Student Name',
            '{date}' => now()->format('M d, Y'),
            '{score}' => '82.7',
            '{total_points}' => '745',
            '{cefr}' => 'C1.2',
            '{actfl}' => 'Advanced High',
            '{exam}' => 'Sample Exam Name',
            '{number}' => 'CERT-SAMPLE-001',
            '{verification_url}' => url("/verify-certificate/sample-code"),
            '{skills_table}' => '
                <tr><td>Section: Composition</td><td>810/900</td><td>90.0%</td><td>C2</td><td>Superior</td><td>25 Aug. 2022</td></tr>
                <tr><td>Section: Speaking</td><td>680/900</td><td>75.6%</td><td>C1.1</td><td>Advanced Mid +</td><td>25 Aug. 2022</td></tr>
            '
        ];

        $html = str_replace(array_keys($placeholders), array_values($placeholders), $template->content_html);

        $service = app(\App\Services\CertificateService::class);
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($service->wrapHtml($html, $template))
                  ->setPaper('a4', 'landscape');

        return $pdf->download("Template-Preview-{$template->id}.pdf");
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
