<?php

namespace App\Http\Requests\Admin\Skill;

use Illuminate\Foundation\Http\FormRequest;

class StoreSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:skills',
            'short_code' => 'nullable|string|max:10|unique:skills',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'levels_count' => 'nullable|integer|min:0|max:100',
        ];
    }
}
