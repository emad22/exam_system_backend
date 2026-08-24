<?php

namespace App\Http\Requests\Admin\ProductiveSkills;

use Illuminate\Foundation\Http\FormRequest;

class GradeAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grades' => 'required|array|min:1',
            'grades.*.answer_id' => 'required|integer|exists:student_answers,id',
            'grades.*.points_awarded' => 'required|numeric|min:0',
            'grades.*.teacher_feedback' => 'nullable|string',
            'grades.*.grading_details' => 'nullable|array',
        ];
    }
}
