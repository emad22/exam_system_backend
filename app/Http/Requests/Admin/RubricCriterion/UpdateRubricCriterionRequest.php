<?php

namespace App\Http\Requests\Admin\RubricCriterion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRubricCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skill_type' => 'nullable|string|max:50',
            'category' => 'required|string|max:100',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'percentage' => 'required|numeric|min:0|max:100',
            'max_points' => 'required|numeric|min:0',
            'order_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
