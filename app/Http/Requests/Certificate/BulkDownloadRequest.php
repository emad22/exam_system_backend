<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class BulkDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate_ids' => 'nullable|array',
            'certificate_ids.*' => 'integer|exists:certificates,id',
            'partner_id' => 'nullable|integer|exists:partners,id',
        ];
    }
}
