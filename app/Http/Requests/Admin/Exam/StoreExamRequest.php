<?php

namespace App\Http\Requests\Admin\Exam;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'language_id' => 'nullable|exists:languages,id',
            'passing_score' => 'required|numeric|min:0|max:100',
            'timer_type' => 'nullable|string',
            'time_limit' => 'nullable|integer',
            'skills' => 'required|array|min:1',
            'skills.*.skill_id' => 'required|exists:skills,id',
            'skills.*.duration' => 'required|integer|min:1',
            'skills.*.is_optional' => 'boolean',
            'skills.*.max_points' => 'nullable|integer|min:0',
            'skills.*.rules' => 'nullable|array',
            'skills.*.rules.*.level_id' => 'required|integer|min:1',
            'skills.*.rules.*.quantity' => 'required|integer|min:0',
            'skills.*.rules.*.standalone_quantity' => 'nullable|integer|min:0',
            'skills.*.rules.*.passage_quantity' => 'nullable|integer|min:0',
            'skills.*.rules.*.randomize' => 'boolean',
            'question_ids' => 'nullable|array',
            'question_ids.*' => 'exists:questions,id',
        ];
    }
}
