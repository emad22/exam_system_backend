<?php

namespace App\Http\Requests\Admin\ProductiveSkills;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'points_awarded' => 'required|numeric|min:0',
            'teacher_feedback' => 'nullable|string',
            'grading_details' => 'nullable|array',
        ];
    }
}
