<?php

namespace App\Http\Requests\Admin\Skill;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $skillId = $this->route('skill')->id ?? null;

        return [
            'name' => 'sometimes|required|string|max:255|unique:skills,name,' . $skillId,
            'short_code' => 'sometimes|nullable|string|max:10|unique:skills,short_code,' . $skillId,
            'description' => 'sometimes|nullable|string',
            'icon' => 'sometimes|nullable|string',
            'levels_count' => 'sometimes|integer|min:0|max:100',
        ];
    }
}
