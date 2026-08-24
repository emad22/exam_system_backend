<?php

namespace App\Http\Requests\Admin\Student;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStudentSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emails'    => 'required|array',
            'emails.*'  => 'string',
            'skills'    => 'required|array',
            'skills.*'  => 'nullable',
        ];
    }
}
