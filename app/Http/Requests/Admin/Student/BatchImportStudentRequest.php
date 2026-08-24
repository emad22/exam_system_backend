<?php

namespace App\Http\Requests\Admin\Student;

use Illuminate\Foundation\Http\FormRequest;

class BatchImportStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'              => 'required|file|max:10240',
            'partner_id'        => 'nullable',
            'package_id'        => 'nullable|exists:packages,id',
            'exam_category_id'  => 'nullable|exists:exam_categories,id',
            'assigned_skills'   => 'nullable',
        ];
    }
}
