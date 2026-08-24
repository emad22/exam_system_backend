<?php

namespace App\Http\Requests\Admin\Proctoring;

use Illuminate\Foundation\Http\FormRequest;

class RecordSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skill_id' => 'required|exists:skills,id',
        ];
    }
}
