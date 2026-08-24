<?php

namespace App\Http\Requests\ExamProgress;

use Illuminate\Foundation\Http\FormRequest;

class SkipQuestionRequest extends FormRequest
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
