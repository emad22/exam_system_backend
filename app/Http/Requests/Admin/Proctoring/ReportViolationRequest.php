<?php

namespace App\Http\Requests\Admin\Proctoring;

use Illuminate\Foundation\Http\FormRequest;

class ReportViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'violation_type' => 'required|string',
            'severity' => 'required|in:info,low,medium,high,critical',
            'description' => 'nullable|string',
            'evidence' => 'nullable|array',
        ];
    }
}
