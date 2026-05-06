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
        return response()->json(CertificateTemplate::latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content_html' => 'required|string',
            'background_image' => 'nullable|image|max:2048',
            'is_default' => 'boolean'
        ]);

        $data = $request->only(['name', 'content_html', 'is_default']);

        if ($request->hasFile('background_image')) {
            $data['background_image'] = $request->file('background_image')->store('templates', 'public');
        }

        if ($data['is_default'] ?? false) {
            CertificateTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = CertificateTemplate::create($data);

        return response()->json($template, 201);
    }

    public function show(CertificateTemplate $template)
    {
        return response()->json($template);
    }

    public function update(Request $request, CertificateTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content_html' => 'required|string',
            'background_image' => 'nullable|image|max:2048',
            'is_default' => 'boolean'
        ]);

        $data = $request->only(['name', 'content_html', 'is_default']);

        if ($request->hasFile('background_image')) {
            // Delete old image if exists
            if ($template->background_image) {
                Storage::disk('public')->delete($template->background_image);
            }
            $data['background_image'] = $request->file('background_image')->store('templates', 'public');
        }

        if ($data['is_default'] ?? false) {
            CertificateTemplate::where('id', '!=', $template->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template->update($data);

        return response()->json($template);
    }

    public function destroy(CertificateTemplate $template)
    {
        if ($template->background_image) {
            Storage::disk('public')->delete($template->background_image);
        }
        $template->delete();
        return response()->json(['message' => 'Template deleted successfully']);
    }

    public function previewPdf(CertificateTemplate $template)
    {
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

        // We use the service's wrap method logic
        $service = app(\App\Services\CertificateService::class);
        
        // Use a dirty hack to access protected method or just re-implement wrap logic here
        // For simplicity and accuracy, let's just use the service logic
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($this->wrapInBaseStyle($html, $template))
                  ->setPaper('a4', 'landscape');

        return $pdf->download("Template-Preview-{$template->id}.pdf");
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
                .container {
                    padding: 50px;
                    text-align: center;
                }
                .content {
                    margin-top: 150px;
                }
            </style>
        </head>
        <body>
            {$content}
        </body>
        </html>
        ";
    }
}
