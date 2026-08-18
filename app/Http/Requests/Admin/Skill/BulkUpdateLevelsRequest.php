<?php

namespace App\Http\Requests\Admin\Skill;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateLevelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'levels' => 'required|array',
            'levels.*.id' => 'nullable|exists:levels,id',
            'levels.*.name' => 'required|string|max:255',
            'levels.*.level_number' => 'required|integer',
            'levels.*.min_score' => 'required|integer',
            'levels.*.max_score' => 'required|integer',
            'levels.*.pass_threshold' => 'required|integer|min:0|max:100',
            'levels.*.instructions' => 'nullable|string',
            'levels.*.default_question_count' => 'nullable|integer|min:0',
        ];
    }
}
