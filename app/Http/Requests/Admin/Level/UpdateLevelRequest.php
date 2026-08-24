<?php

namespace App\Http\Requests\Admin\Level;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skill_id' => 'sometimes|required|exists:skills,id',
            'name' => 'sometimes|required|string|max:255',
            'level_number' => 'sometimes|required|integer',
            'min_score' => 'sometimes|required|integer',
            'max_score' => 'sometimes|required|integer',
            'pass_threshold' => 'sometimes|required|integer|min:0|max:100',
            'default_question_count' => 'nullable|integer|min:0',
            'instructions' => 'nullable|string',
            'instructions_audio' => 'nullable|file|mimes:mp3,wav|max:5120',
            'is_active' => 'sometimes|boolean',
            'is_random' => 'sometimes|boolean',
            'allows_retry' => 'sometimes|boolean',
            'default_standalone_quantity' => 'nullable|integer|min:0',
            'default_passage_quantity' => 'nullable|integer|min:0',
        ];
    }
}
