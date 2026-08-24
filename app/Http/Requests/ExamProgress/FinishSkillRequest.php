<?php

namespace App\Http\Requests\ExamProgress;

use Illuminate\Foundation\Http\FormRequest;

class FinishSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_id' => 'required|exists:questions,id',
        ];
    }
}
