<?php

namespace App\Http\Requests\Admin\CertificateTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'content_html' => 'required|string',
            'background_image' => 'nullable|image|max:2048',
            'is_default' => 'sometimes|boolean',
            'elements_json' => 'nullable|string',
            'background_settings' => 'nullable|string',
        ];
    }
}
