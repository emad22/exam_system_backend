<?php

namespace App\Http\Requests\Admin\Level;

use Illuminate\Foundation\Http\FormRequest;

class StoreLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skill_id' => 'required|exists:skills,id',
            'level_number' => 'required|integer',
            'min_score' => 'required|integer',
            'max_score' => 'required|integer',
            'pass_threshold' => 'required|integer|min:0|max:100',
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
